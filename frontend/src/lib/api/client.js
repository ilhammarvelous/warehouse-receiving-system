import { apiErrorFromResponse, ApiError } from "@/lib/api/errors";
import {
  clearAccessToken,
  getAccessToken,
} from "@/lib/auth/token-storage";
import { AUTH_UNAUTHORIZED_EVENT } from "@/lib/auth/events";

function notifyUnauthorized() {
  if (typeof window !== "undefined") {
    window.dispatchEvent(new Event(AUTH_UNAUTHORIZED_EVENT));
  }
}

function apiUrl(path) {
  const baseUrl = process.env.NEXT_PUBLIC_API_URL?.trim();

  if (!baseUrl) {
    throw new Error("NEXT_PUBLIC_API_URL must be configured.");
  }

  const normalizedPath = path.startsWith("/") ? path : `/${path}`;
  return `${baseUrl.replace(/\/+$/, "")}${normalizedPath}`;
}

export async function apiRequest(
  path,
  { method = "GET", body, headers, auth = true } = {}
) {
  const requestHeaders = new Headers(headers);
  requestHeaders.set("Accept", "application/json");
  requestHeaders.set("Content-Type", "application/json");

  if (auth) {
    const token = getAccessToken();
    if (token) {
      requestHeaders.set("Authorization", `Bearer ${token}`);
    }
  }

  const response = await fetch(apiUrl(path), {
    method,
    headers: requestHeaders,
    ...(body === undefined ? {} : { body: JSON.stringify(body) }),
  });

  let responseBody = null;
  if (response.status !== 204) {
    try {
      responseBody = await response.json();
    } catch (error) {
      if (!(error instanceof SyntaxError)) {
        throw error;
      }

      if (response.ok) {
        throw new ApiError(
          response.status,
          "INVALID_RESPONSE",
          "The server returned an invalid response."
        );
      }
    }
  }

  if (!response.ok) {
    if (response.status === 401) {
      clearAccessToken();
      notifyUnauthorized();
    }

    throw apiErrorFromResponse(response.status, responseBody);
  }

  return responseBody;
}
