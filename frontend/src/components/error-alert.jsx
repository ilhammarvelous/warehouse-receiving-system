import {
  Alert,
  AlertDescription,
  AlertTitle,
} from "@/components/ui/alert";
import { ApiError } from "@/lib/api/errors";

function fieldErrors(error) {
  const fields = error?.details?.fields;
  if (!fields || typeof fields !== "object" || Array.isArray(fields)) {
    return [];
  }

  return Object.entries(fields).flatMap(([field, messages]) => {
    if (!Array.isArray(messages)) {
      return [];
    }

    return messages
      .filter((message) => typeof message === "string")
      .map((message) => ({ field, message }));
  });
}

export function ErrorAlert({
  error,
  message,
  title = "Something went wrong",
  includeFieldErrors = true,
}) {
  const errors = includeFieldErrors ? fieldErrors(error) : [];
  const description =
    message ??
    (error instanceof Error
      ? error.message
      : typeof error === "string"
        ? error
        : "Please try again.");
  const code = error instanceof ApiError ? error.code : null;

  return (
    <Alert variant="destructive" role="alert">
      <AlertTitle>{title}</AlertTitle>
      <AlertDescription className="space-y-2">
        <p>{description}</p>
        {code && <p className="font-medium">{code}</p>}
        {errors.length > 0 && (
          <ul className="list-inside list-disc space-y-1">
            {errors.map(({ field, message: fieldMessage }, index) => (
              <li key={`${field}-${index}`}>
                <span className="font-medium">{field}:</span> {fieldMessage}
              </li>
            ))}
          </ul>
        )}
      </AlertDescription>
    </Alert>
  );
}
