import { ApiError } from "@/lib/api/errors";

export function getIdempotencyAttempt(attempt, payload, createKey) {
  const fingerprint = JSON.stringify(payload);

  if (attempt?.fingerprint === fingerprint) {
    return attempt;
  }

  const generateKey =
    createKey ??
    (() => {
      if (typeof crypto === "undefined" || !crypto.randomUUID) {
        throw new Error("crypto.randomUUID() is required for receipt requests.");
      }

      return crypto.randomUUID();
    });

  return {
    fingerprint,
    key: generateKey(),
  };
}

export function shouldDiscardIdempotencyKey(error) {
  if (!(error instanceof ApiError)) {
    return false;
  }

  return (
    (error.status >= 200 && error.status < 300) ||
    (error.status >= 400 && error.status < 500)
  );
}
