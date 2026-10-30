import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { ErrorAlert } from "@/components/error-alert";

export function PlaceholderPage({ title, description, notice }) {
  return (
    <section className="mx-auto w-full max-w-5xl space-y-4">
      {notice && (
        <ErrorAlert
          title="Access restricted"
          message={notice}
        />
      )}
      <Card>
        <CardHeader>
          <CardTitle>{title}</CardTitle>
          <CardDescription>{description}</CardDescription>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-muted-foreground">
            This page will be implemented in a later frontend task.
          </p>
        </CardContent>
      </Card>
    </section>
  );
}
