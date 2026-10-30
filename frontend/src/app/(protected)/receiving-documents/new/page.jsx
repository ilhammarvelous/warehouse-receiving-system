"use client";

import { useRouter } from "next/navigation";
import { ReceivingDocumentForm } from "@/components/receiving-document-form";
import { RoleGuard } from "@/components/role-guard";
import { useCreateReceivingDocument } from "@/lib/api/receiving-documents";

export default function NewReceivingDocumentPage() {
  const router = useRouter();
  const createDocument = useCreateReceivingDocument();

  async function submitDocument(document) {
    const response = await createDocument.mutateAsync(document);
    router.push(`/receiving-documents/${response.data.id}`);
  }

  return (
    <RoleGuard allowedRoles={["SUPERVISOR"]}>
      <section className="mx-auto w-full max-w-4xl">
        <ReceivingDocumentForm onSubmit={submitDocument} />
      </section>
    </RoleGuard>
  );
}
