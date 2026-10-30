import { PlaceholderPage } from "@/components/placeholder-page";
import { RoleGuard } from "@/components/role-guard";

export default function NewReceivingDocumentPage() {
  return (
    <RoleGuard allowedRoles={["SUPERVISOR"]}>
      <PlaceholderPage
        title="New receiving document"
        description="Create an inbound delivery document and add its expected items."
      />
    </RoleGuard>
  );
}
