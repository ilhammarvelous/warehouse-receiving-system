import { z } from "zod";

export function createReceiptSchema(remainingQuantity) {
  return z.object({
    quantity: z.coerce
      .number()
      .int()
      .min(1)
      .max(remainingQuantity),
  });
}
