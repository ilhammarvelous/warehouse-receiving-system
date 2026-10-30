"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";
import { apiRequest } from "@/lib/api/client";
import { ApiError } from "@/lib/api/errors";
import { AUTH_UNAUTHORIZED_EVENT } from "@/lib/auth/events";
import {
  clearAccessToken,
  getAccessToken,
  storeAccessToken,
} from "@/lib/auth/token-storage";
import { toast } from "@/components/ui/toast";

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [authState, setAuthState] = useState({
    status: "loading",
    user: null,
    error: null,
  });

  const refreshUser = useCallback(async () => {
    const token = getAccessToken();
    if (!token) {
      setAuthState({ status: "unauthenticated", user: null, error: null });
      return false;
    }

    setAuthState({ status: "loading", user: null, error: null });

    try {
      const response = await apiRequest("/auth/me");
      setAuthState({
        status: "authenticated",
        user: response.data,
        error: null,
      });
      return true;
    } catch (error) {
      const isUnauthorized = error instanceof ApiError && error.status === 401;
      setAuthState({
        status: isUnauthorized ? "unauthenticated" : "error",
        user: null,
        error: isUnauthorized ? null : error,
      });
      return false;
    }
  }, []);

  useEffect(() => {
    let active = true;

    const handleUnauthorized = () => {
      clearAccessToken();
      setAuthState({ status: "unauthenticated", user: null, error: null });
    };

    window.addEventListener(AUTH_UNAUTHORIZED_EVENT, handleUnauthorized);
    void Promise.resolve().then(() => {
      if (active) {
        return refreshUser();
      }

      return false;
    });

    return () => {
      active = false;
      window.removeEventListener(AUTH_UNAUTHORIZED_EVENT, handleUnauthorized);
    };
  }, [refreshUser]);

  const login = useCallback(async (credentials) => {
    const response = await apiRequest("/auth/login", {
      method: "POST",
      body: credentials,
      auth: false,
    });

    storeAccessToken(response.data.token);
    setAuthState({
      status: "authenticated",
      user: response.data.user,
      error: null,
    });
    toast.success("Signed in successfully.");

    return response.data.user;
  }, []);

  const logout = useCallback(async () => {
    await apiRequest("/auth/logout", { method: "POST" });
    clearAccessToken();
    setAuthState({ status: "unauthenticated", user: null, error: null });
    toast.success("Signed out successfully.");
  }, []);

  const value = useMemo(
    () => ({
      ...authState,
      login,
      logout,
      refreshUser,
    }),
    [authState, login, logout, refreshUser]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);

  if (context === null) {
    throw new Error("useAuth must be used within an AuthProvider.");
  }

  return context;
}
