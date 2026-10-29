---
applyTo: "frontend/**"
---

# Frontend instructions (Next.js, JavaScript, shadcn/ui)

Source of truth: `docs/technical-spec.md` sections 1.2, 5 (especially 5.6); `docs/openapi.yaml`; `docs/prd.md` section 5.7 (FR-UI-*).

## Language and structure

- Next.js App Router, **JavaScript only** (`.js`, `.jsx`). No TypeScript, no `tsconfig.json`, no generated type files. Path alias `@/*` from `jsconfig.json`. Node.js 24.22.1.
- Folder layout as in `docs/technical-spec.md` section 3: `src/app`, `src/components`, `src/components/ui` (shadcn), `src/lib/api`, `src/lib/schemas`, `src/lib/auth`, `src/lib/utils.js`.
- `src/lib/api/client.js` is the only code that calls `fetch`. It adds the base URL (`NEXT_PUBLIC_API_URL`), headers, and the bearer token, and turns error responses into an `ApiError` (`status`, `code`, `message`, `details`). On `401` it clears the token and redirects to `/login`.
- One TanStack Query hook per resource. After a successful receipt, invalidate the document detail, document list, inventory, and transactions queries.

## UI: shadcn/ui only

- **Every UI element is a shadcn/ui component** from `src/components/ui`. Pages and app components are composed from them, plus Tailwind utility classes for layout and spacing only.
- Preset: Luma style, Mist base color, Emerald theme, Emerald chart color, Outfit heading and body font, Lucide icons, default radius. Do not change the preset, and do not hard-code colors, font families, or radius values: use theme tokens (`bg-primary`, `text-muted-foreground`, `border`, ...).
- Use the component mapping table in `docs/technical-spec.md` section 5.6. Add missing components with `npx shadcn@latest add <name>`. If a component does not exist in the installed CLI, report it.
- Do not install or use any other UI library. Icons come only from `lucide-react`.
- Do not hand-build buttons, tables, dialogs, badges, alerts, inputs, or selects. Do not edit `src/components/ui/*` except to fix a real defect.
- Confirm, Complete, and Cancel always use an `AlertDialog` before sending the request. Status is shown with one shared `StatusBadge` built from `Badge`.
- Every input has a `Label`. Keep the accessibility behavior shadcn provides.
- If the shadcn CLI generates `.tsx` files, stop and report it. Do not convert by hand.

## Forms and validation

- One Zod schema per form in `src/lib/schemas`, mirroring `docs/prd.md` section 8.2. Use React Hook Form with `zodResolver`.
- Zod is a usability hint. Copy server `details.fields` into the form with `setError`. Never assume the server accepts what Zod accepted.

## Behavior

- Do not compute business rules. Show `remaining_quantity`, `all_items_received`, and `balance_after` exactly as the API returns them.
- Show or disable actions by role and document status as in `docs/technical-spec.md` section 5.3. This is guidance only; the backend enforces everything.
- Receipt form idempotency (section 5.4): create a key with `crypto.randomUUID()` when the user submits, disable the button while in flight, keep the same key after a network error, timeout, or 5xx, and discard it after any 2xx or 4xx answer.
- Every page has loading, empty, and error states (use `Skeleton`, `Empty`, `Alert`). Show field errors from `details.fields`. Unknown error codes show the API message and must not crash the page.
- Token handling: `localStorage` mirrored in an `AuthProvider`. Never log the token.
- Never use `dangerouslySetInnerHTML`.
- `NEXT_PUBLIC_API_URL` is the only API base URL setting. Do not hard-code URLs.

## Tests

- Vitest and React Testing Library for the API client, the idempotency key logic, the receipt form, the Zod schemas, and role-aware buttons.
- Playwright end-to-end scenarios from `docs/technical-spec.md` section 15.4 against the Docker Compose stack.
- Checks that must pass: `npm run lint` and `npm test`.
