import { ApiError } from "@/lib/api/errors";

export function applyServerFieldErrors(error, setError) {
  const fields = error instanceof ApiError ? error.details?.fields : null;

  if (!fields || typeof fields !== "object" || Array.isArray(fields)) {
    return false;
  }

  let applied = false;
  Object.entries(fields).forEach(([field, messages]) => {
    if (Array.isArray(messages) && typeof messages[0] === "string") {
      setError(field, { type: "server", message: messages[0] });
      applied = true;
    }
  });

  return applied;
}
