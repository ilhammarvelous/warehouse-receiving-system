import { z } from "zod";

const apiErrorResponseSchema = z.object({
  error: z.object({
    code: z.string().min(1),
    message: z.string(),
    details: z.record(z.string(), z.unknown()).nullable().optional(),
  }),
});

export class ApiError extends Error {
  constructor(status, code, message, details = null) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.code = code;
    this.details = details;
  }
}

export function apiErrorFromResponse(status, body) {
  const parsed = apiErrorResponseSchema.safeParse(body);

  if (!parsed.success) {
    return new ApiError(
      status,
      "INVALID_ERROR_RESPONSE",
      "The server returned an invalid error response."
    );
  }

  const { code, message, details } = parsed.data.error;
  return new ApiError(status, code, message, details ?? null);
}
