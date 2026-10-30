import {
  Ban,
  Circle,
  CircleCheck,
  CircleDot,
  TriangleAlert,
} from "lucide-react";
import { Badge } from "@/components/ui/badge";

const statusPresentation = {
  DRAFT: {
    label: "DRAFT",
    variant: "secondary",
    icon: CircleDot,
  },
  OPEN: {
    label: "OPEN",
    variant: "outline",
    icon: Circle,
  },
  PARTIALLY_RECEIVED: {
    label: "PARTIALLY_RECEIVED",
    variant: "secondary",
    icon: TriangleAlert,
  },
  COMPLETED: {
    label: "COMPLETED",
    variant: "default",
    icon: CircleCheck,
  },
  CANCELLED: {
    label: "CANCELLED",
    variant: "destructive",
    icon: Ban,
  },
};

export function StatusBadge({ status }) {
  const presentation = statusPresentation[status] ?? {
    label: status || "UNKNOWN",
    variant: "secondary",
    icon: CircleDot,
  };
  const Icon = presentation.icon;

  return (
    <Badge variant={presentation.variant}>
      <Icon aria-hidden="true" />
      {presentation.label}
    </Badge>
  );
}
