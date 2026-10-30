import { z } from "zod";

const receivingItemSchema = z.object({
  product_id: z.coerce.number().int().positive(),
  expected_quantity: z.coerce.number().int().min(1).max(1_000_000),
});

export const receivingDocumentSchema = z
  .object({
    supplier_name: z.string().trim().min(1).max(255),
    expected_date: z.iso.date(),
    notes: z.string().max(1000).nullable().optional(),
    items: z.array(receivingItemSchema).min(1).max(100),
  })
  .superRefine((document, context) => {
    const seenProductIds = new Set();

    document.items.forEach((item, index) => {
      if (seenProductIds.has(item.product_id)) {
        context.addIssue({
          code: "custom",
          message: "A product can appear only once in a receiving document.",
          path: ["items", index, "product_id"],
        });
      }

      seenProductIds.add(item.product_id);
    });
  });
