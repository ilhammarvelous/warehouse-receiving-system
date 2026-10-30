import { cleanup } from "@testing-library/react";
import "@testing-library/jest-dom/vitest";
import { afterEach } from "vitest";

process.env.NEXT_PUBLIC_API_URL ??= "http://localhost:8000/api/v1";

afterEach(() => {
  cleanup();
});
