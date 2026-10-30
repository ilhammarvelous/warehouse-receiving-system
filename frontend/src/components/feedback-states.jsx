import { Inbox } from "lucide-react";
import {
  Empty,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty";
import { Skeleton } from "@/components/ui/skeleton";
import { Spinner } from "@/components/ui/spinner";

export function LoadingState({ label = "Loading..." }) {
  return (
    <div
      className="flex min-h-48 flex-col items-center justify-center gap-4"
      role="status"
      aria-live="polite"
    >
      <Spinner />
      <span className="text-sm text-muted-foreground">{label}</span>
      <div className="w-full max-w-xl space-y-2" aria-hidden="true">
        <Skeleton className="h-4 w-3/4" />
        <Skeleton className="h-4 w-1/2" />
      </div>
    </div>
  );
}

export function EmptyState({
  title = "Nothing here yet",
  description = "There is no data to display.",
}) {
  return (
    <Empty>
      <EmptyHeader>
        <EmptyMedia variant="icon">
          <Inbox aria-hidden="true" />
        </EmptyMedia>
        <EmptyTitle>{title}</EmptyTitle>
        <EmptyDescription>{description}</EmptyDescription>
      </EmptyHeader>
    </Empty>
  );
}
