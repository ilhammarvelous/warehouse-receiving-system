"use client";

import { useEffect } from "react";
import { useParams, useRouter } from "next/navigation";
import { ErrorAlert } from "@/components/error-alert";
import { LoadingState } from "@/components/feedback-states";
import { ReceivingDocumentForm } from "@/components/receiving-document-form";
import { RoleGuard } from "@/components/role-guard";
import {
  useReceivingDocument,
  useUpdateReceivingDocument,
} from "@/lib/api/receiving-documents";

export default function EditReceivingDocumentPage() {
  const { id } = useParams();
  const router = useRouter();
  const documentQuery = useReceivingDocument(id);
  const updateDocument = useUpdateReceivingDocument();
  const document = documentQuery.data?.data;

  useEffect(() => {
    if (document && document.status !== "DRAFT") {
      router.replace(`/receiving-documents/${id}`);
    }
  }, [document, id, router]);

  async function submitDocument(payload) {
    await updateDocument.mutateAsync({ id, document: payload });
    router.push(`/receiving-documents/${id}`);
  }

  return (
    <RoleGuard allowedRoles={["SUPERVISOR"]}>
      <section className="mx-auto w-full max-w-4xl">
        {documentQuery.isPending && (
          <LoadingState label="Loading draft document..." />
        )}
        {documentQuery.isError && <ErrorAlert error={documentQuery.error} />}
        {document?.status === "DRAFT" && (
          <ReceivingDocumentForm
            key={document.id}
            initialData={document}
            submitLabel="Save changes"
            onSubmit={submitDocument}
          />
        )}
      </section>
    </RoleGuard>
  );
}
