import { Button } from "@/components/ui/button";

export default function Home() {
  return (
    <main className="flex min-h-screen flex-col items-center justify-center gap-4 bg-background px-6 text-center text-foreground">
      <h1 className="text-3xl font-semibold tracking-tight">
        Warehouse Receiving System
      </h1>
      <p className="max-w-md text-muted-foreground">
        The application foundation is ready for the receiving workflows.
      </p>
      <Button disabled>Frontend scaffold ready</Button>
    </main>
  );
}
