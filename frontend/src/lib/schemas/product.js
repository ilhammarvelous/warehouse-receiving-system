import { z } from "zod";

export const productSchema = z.object({
  sku: z
    .string()
    .trim()
    .toUpperCase()
    .min(1)
    .max(50)
    .regex(/^[A-Z0-9](?:[A-Z0-9-]*[A-Z0-9])?$/),
  name: z.string().trim().min(1).max(255),
});
