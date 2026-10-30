import { act, renderHook, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { AuthProvider, useAuth } from "@/lib/auth/AuthProvider";
import { AUTH_UNAUTHORIZED_EVENT } from "@/lib/auth/events";
import { getAccessToken } from "@/lib/auth/token-storage";

describe("AuthProvider", () => {
  const fetchMock = vi.fn();

  beforeEach(() => {
    localStorage.clear();
    fetchMock.mockReset();
    vi.stubGlobal("fetch", fetchMock);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("loads the current user for a stored token", async () => {
    localStorage.setItem("warehouse-receiving.access-token", "stored-token");
    fetchMock.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({
        data: {
          id: 1,
          name: "Warehouse Supervisor",
          email: "supervisor@example.com",
          role: "SUPERVISOR",
        },
      }),
    });

    const { result } = renderHook(() => useAuth(), {
      wrapper: ({ children }) => <AuthProvider>{children}</AuthProvider>,
    });

    await waitFor(() => {
      expect(result.current.status).toBe("authenticated");
    });
    expect(result.current.user.role).toBe("SUPERVISOR");
    expect(fetchMock).toHaveBeenCalledWith(
      "http://localhost:8000/api/v1/auth/me",
      expect.any(Object)
    );
  });

  it("stores the token and user after a successful login", async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({
        data: {
          token: "new-token",
          token_type: "Bearer",
          user: {
            id: 2,
            name: "Warehouse Operator",
            email: "operator@example.com",
            role: "OPERATOR",
          },
        },
      }),
    });

    const { result } = renderHook(() => useAuth(), {
      wrapper: ({ children }) => <AuthProvider>{children}</AuthProvider>,
    });

    await waitFor(() => {
      expect(result.current.status).toBe("unauthenticated");
    });

    await act(async () => {
      await result.current.login({
        email: "operator@example.com",
        password: "password",
      });
    });

    expect(result.current.status).toBe("authenticated");
    expect(result.current.user.role).toBe("OPERATOR");
    expect(getAccessToken()).toBe("new-token");
    expect(fetchMock).toHaveBeenCalledWith(
      "http://localhost:8000/api/v1/auth/login",
      expect.objectContaining({ method: "POST" })
    );
  });

  it("revokes the server session and clears the local token on logout", async () => {
    localStorage.setItem("warehouse-receiving.access-token", "stored-token");
    fetchMock
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          data: {
            id: 1,
            name: "Warehouse Supervisor",
            email: "supervisor@example.com",
            role: "SUPERVISOR",
          },
        }),
      })
      .mockResolvedValueOnce({ ok: true, status: 204 });

    const { result } = renderHook(() => useAuth(), {
      wrapper: ({ children }) => <AuthProvider>{children}</AuthProvider>,
    });

    await waitFor(() => {
      expect(result.current.status).toBe("authenticated");
    });

    await act(async () => {
      await result.current.logout();
    });

    expect(result.current.status).toBe("unauthenticated");
    expect(getAccessToken()).toBeNull();
    expect(fetchMock.mock.calls[1][1].method).toBe("POST");
  });

  it("clears the session after the API client reports an unauthorized response", async () => {
    localStorage.setItem("warehouse-receiving.access-token", "stored-token");
    fetchMock.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({
        data: {
          id: 1,
          name: "Warehouse Supervisor",
          email: "supervisor@example.com",
          role: "SUPERVISOR",
        },
      }),
    });

    const { result } = renderHook(() => useAuth(), {
      wrapper: ({ children }) => <AuthProvider>{children}</AuthProvider>,
    });

    await waitFor(() => {
      expect(result.current.status).toBe("authenticated");
    });

    act(() => {
      window.dispatchEvent(new Event(AUTH_UNAUTHORIZED_EVENT));
    });

    await waitFor(() => {
      expect(result.current.status).toBe("unauthenticated");
    });
    expect(getAccessToken()).toBeNull();
  });
});
