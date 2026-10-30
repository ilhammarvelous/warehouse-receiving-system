import { PlaceholderPage } from "@/components/placeholder-page";
import { RoleGuard } from "@/components/role-guard";

export default function EditReceivingDocumentPage() {
  return (
    <RoleGuard allowedRoles={["SUPERVISOR"]}>
      <PlaceholderPage
        title="Edit receiving document"
        description="Update a draft receiving document."
      />
    </RoleGuard>
  );
}
