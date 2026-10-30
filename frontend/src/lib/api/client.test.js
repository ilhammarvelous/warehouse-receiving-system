import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { apiRequest } from "@/lib/api/client";
import { ApiError } from "@/lib/api/errors";
import {
  getAccessToken,
  storeAccessToken,
} from "@/lib/auth/token-storage";

describe("apiRequest", () => {
  const fetchMock = vi.fn();

  beforeEach(() => {
    localStorage.clear();
    vi.stubGlobal("fetch", fetchMock);
    fetchMock.mockReset();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("uses the API base URL, JSON headers, and stored bearer token", async () => {
    storeAccessToken("test-token");
    fetchMock.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ data: { id: 1 } }),
    });

    await expect(apiRequest("/auth/me")).resolves.toEqual({
      data: { id: 1 },
    });

    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toBe("http://localhost:8000/api/v1/auth/me");
    expect(options.headers.get("Accept")).toBe("application/json");
    expect(options.headers.get("Content-Type")).toBe("application/json");
    expect(options.headers.get("Authorization")).toBe("Bearer test-token");
  });

  it("turns the standard API error shape into an ApiError", async () => {
    fetchMock.mockResolvedValue({
      ok: false,
      status: 422,
      json: async () => ({
        error: {
          code: "VALIDATION_ERROR",
          message: "The given data was invalid.",
          details: {
            fields: { email: ["The email field is required."] },
          },
        },
      }),
    });

    await expect(apiRequest("/auth/login", { auth: false })).rejects.toMatchObject({
      name: "ApiError",
      status: 422,
      code: "VALIDATION_ERROR",
      message: "The given data was invalid.",
      details: { fields: { email: ["The email field is required."] } },
    });
    await expect(
      apiRequest("/auth/login", { auth: false })
    ).rejects.toBeInstanceOf(ApiError);
  });

  it("clears the token and redirects to login after a 401 response", async () => {
    const dispatchEvent = vi.fn();
    storeAccessToken("expired-token");
    vi.stubGlobal("window", {
      localStorage,
      dispatchEvent,
    });
    fetchMock.mockResolvedValue({
      ok: false,
      status: 401,
      json: async () => ({
        error: { code: "UNAUTHENTICATED", message: "Unauthenticated." },
      }),
    });

    await expect(apiRequest("/auth/me")).rejects.toBeInstanceOf(ApiError);

    expect(getAccessToken()).toBeNull();
    expect(dispatchEvent).toHaveBeenCalledWith(
      expect.objectContaining({ type: "warehouse-auth-unauthorized" })
    );
  });

  it("does not replace a malformed error response with a success-shaped value", async () => {
    fetchMock.mockResolvedValue({
      ok: false,
      status: 500,
      json: async () => ({ message: "Internal Server Error" }),
    });

    await expect(apiRequest("/auth/me")).rejects.toMatchObject({
      name: "ApiError",
      status: 500,
      code: "INVALID_ERROR_RESPONSE",
    });
  });
});
