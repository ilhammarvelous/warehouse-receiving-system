#!/usr/bin/env bash

set -Eeuo pipefail

api_base_url="${API_BASE_URL:-http://localhost:8000/api/v1}"
supervisor_email="${SEED_SUPERVISOR_EMAIL:-supervisor@example.com}"
supervisor_password="${SEED_SUPERVISOR_PASSWORD:-change-me-supervisor}"
operator_email="${SEED_OPERATOR_EMAIL:-operator@example.com}"
operator_password="${SEED_OPERATOR_PASSWORD:-change-me-operator}"
temp_dir="$(mktemp -d)"

cleanup() {
  rm -f -- "$temp_dir"/supervisor-login.json "$temp_dir"/operator-login.json \
    "$temp_dir"/product.json "$temp_dir"/document.json "$temp_dir"/inventory-before.json \
    "$temp_dir"/inventory-after.json "$temp_dir"/receipt-one.json "$temp_dir"/receipt-two.json \
    "$temp_dir"/status-one.txt "$temp_dir"/status-two.txt
  rmdir -- "$temp_dir"
}
trap cleanup EXIT

json_field() {
  node -e '
    let input = "";
    process.stdin.setEncoding("utf8");
    process.stdin.on("data", (chunk) => { input += chunk; });
    process.stdin.on("end", () => {
      const value = process.argv[1].split(".").reduce((current, key) => current[key], JSON.parse(input));
      if (value === undefined || value === null) process.exit(1);
      console.log(value);
    });
  ' "$1"
}

request_json() {
  local method="$1"
  local url="$2"
  local token="$3"
  local payload="$4"
  local output="$5"

  if [[ -n "$payload" ]]; then
    curl --fail --silent --show-error --request "$method" "$url" \
      --header "Authorization: Bearer $token" \
      --header 'Accept: application/json' \
      --header 'Content-Type: application/json' \
      --data "$payload" \
      --output "$output"
  else
    curl --fail --silent --show-error --request "$method" "$url" \
      --header "Authorization: Bearer $token" \
      --header 'Accept: application/json' \
      --output "$output"
  fi
}

login_payload="$(node -e 'console.log(JSON.stringify({email: process.argv[1], password: process.argv[2]}))' \
  "$supervisor_email" "$supervisor_password")"
curl --fail --silent --show-error "$api_base_url/auth/login" \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data "$login_payload" \
  --output "$temp_dir/supervisor-login.json"
supervisor_token="$(json_field 'data.token' < "$temp_dir/supervisor-login.json")"

login_payload="$(node -e 'console.log(JSON.stringify({email: process.argv[1], password: process.argv[2]}))' \
  "$operator_email" "$operator_password")"
curl --fail --silent --show-error "$api_base_url/auth/login" \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data "$login_payload" \
  --output "$temp_dir/operator-login.json"
operator_token="$(json_field 'data.token' < "$temp_dir/operator-login.json")"

sku="CONC-$(node -e 'console.log(crypto.randomUUID().replaceAll("-", "").slice(0, 12).toUpperCase())')"
product_payload="$(node -e 'console.log(JSON.stringify({sku: process.argv[1], name: "Concurrency demonstration product"}))' "$sku")"
request_json POST "$api_base_url/products" "$supervisor_token" "$product_payload" "$temp_dir/product.json"
product_id="$(json_field 'data.id' < "$temp_dir/product.json")"

expected_date="$(node -e 'console.log(new Date().toISOString().slice(0, 10))')"
document_payload="$(node -e 'console.log(JSON.stringify({
  supplier_name: "Concurrency demonstration supplier",
  expected_date: process.argv[1],
  items: [{product_id: Number(process.argv[2]), expected_quantity: 5}]
}))' "$expected_date" "$product_id")"
request_json POST "$api_base_url/receiving-documents" "$supervisor_token" \
  "$document_payload" "$temp_dir/document.json"
document_id="$(json_field 'data.id' < "$temp_dir/document.json")"
item_id="$(json_field 'data.items.0.id' < "$temp_dir/document.json")"
document_number="$(json_field 'data.document_number' < "$temp_dir/document.json")"
request_json POST "$api_base_url/receiving-documents/$document_id/confirm" \
  "$supervisor_token" "" "$temp_dir/document.json"

curl --fail --silent --show-error \
  "$api_base_url/inventory?search=$sku&per_page=1" \
  --header "Authorization: Bearer $operator_token" \
  --header 'Accept: application/json' \
  --output "$temp_dir/inventory-before.json"
stock_before="$(json_field 'data.0.quantity_on_hand' < "$temp_dir/inventory-before.json")"

key_one="$(node -e 'console.log(crypto.randomUUID())')"
key_two="$(node -e 'console.log(crypto.randomUUID())')"
receipt_payload='{"quantity":5}'
receipt_url="$api_base_url/receiving-documents/$document_id/items/$item_id/receipts"

curl --silent --show-error --request POST "$receipt_url" \
  --header "Authorization: Bearer $operator_token" \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header "Idempotency-Key: $key_one" \
  --data "$receipt_payload" \
  --output "$temp_dir/receipt-one.json" \
  --write-out '%{http_code}' > "$temp_dir/status-one.txt" &
request_one_pid=$!

curl --silent --show-error --request POST "$receipt_url" \
  --header "Authorization: Bearer $operator_token" \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header "Idempotency-Key: $key_two" \
  --data "$receipt_payload" \
  --output "$temp_dir/receipt-two.json" \
  --write-out '%{http_code}' > "$temp_dir/status-two.txt" &
request_two_pid=$!

wait "$request_one_pid"
wait "$request_two_pid"

status_one="$(<"$temp_dir/status-one.txt")"
status_two="$(<"$temp_dir/status-two.txt")"
curl --fail --silent --show-error \
  "$api_base_url/inventory?search=$sku&per_page=1" \
  --header "Authorization: Bearer $operator_token" \
  --header 'Accept: application/json' \
  --output "$temp_dir/inventory-after.json"
stock_after="$(json_field 'data.0.quantity_on_hand' < "$temp_dir/inventory-after.json")"

printf 'Document: %s\nSKU: %s\nStock before: %s\nStock after: %s\n' \
  "$document_number" "$sku" "$stock_before" "$stock_after"
printf 'Request 1 (%s): HTTP %s, %s\n' \
  "$key_one" "$status_one" "$(json_field 'data.transaction.quantity' < "$temp_dir/receipt-one.json" 2>/dev/null \
    || json_field 'error.code' < "$temp_dir/receipt-one.json")"
printf 'Request 2 (%s): HTTP %s, %s\n' \
  "$key_two" "$status_two" "$(json_field 'data.transaction.quantity' < "$temp_dir/receipt-two.json" 2>/dev/null \
    || json_field 'error.code' < "$temp_dir/receipt-two.json")"

if [[ "$stock_after" -ne $((stock_before + 5)) ]]; then
  printf 'Expected stock to increase by exactly 5; concurrency check failed.\n' >&2
  exit 1
fi

if ! { [[ "$status_one" == '201' && "$status_two" == '422' ]] \
    || [[ "$status_one" == '422' && "$status_two" == '201' ]]; }; then
  printf 'Expected exactly one HTTP 201 and one HTTP 422 response.\n' >&2
  exit 1
fi

losing_body="$temp_dir/receipt-one.json"
if [[ "$status_one" == '201' ]]; then
  losing_body="$temp_dir/receipt-two.json"
fi
if [[ "$(json_field 'error.code' < "$losing_body")" != 'QUANTITY_EXCEEDS_REMAINING' ]]; then
  printf 'The rejected request did not return QUANTITY_EXCEEDS_REMAINING.\n' >&2
  exit 1
fi

printf 'Concurrency check passed: exactly one receipt changed stock.\n'
