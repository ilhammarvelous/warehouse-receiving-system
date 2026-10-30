import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from "@/components/ui/pagination";

function pageNumbers(currentPage, lastPage) {
  const pages = new Set([1, lastPage]);

  for (
    let page = Math.max(1, currentPage - 1);
    page <= Math.min(lastPage, currentPage + 1);
    page += 1
  ) {
    pages.add(page);
  }

  return [...pages].sort((first, second) => first - second);
}

function handlePageClick(event, page, onPageChange) {
  event.preventDefault();
  onPageChange(page);
}

export function PaginationControls({
  page,
  lastPage,
  onPageChange,
  label = "Pagination",
}) {
  if (
    !Number.isInteger(page) ||
    !Number.isInteger(lastPage) ||
    page < 1 ||
    page > lastPage ||
    lastPage < 2
  ) {
    return null;
  }

  const pages = pageNumbers(page, lastPage);

  return (
    <Pagination aria-label={label}>
      <PaginationContent>
        <PaginationItem>
          <PaginationPrevious
            href="#"
            aria-label="Go to previous page"
            aria-disabled={page <= 1}
            tabIndex={page <= 1 ? -1 : undefined}
            onClick={(event) => {
              event.preventDefault();
              if (page > 1) {
                onPageChange(page - 1);
              }
            }}
          />
        </PaginationItem>
        {pages.map((pageNumber, index) => {
          const previousPage = pages[index - 1];
          const hasGap =
            previousPage !== undefined && pageNumber - previousPage > 1;

          return (
            <PaginationItem key={`page-${pageNumber}`}>
              {hasGap && <PaginationEllipsis />}
              <PaginationLink
                href="#"
                isActive={pageNumber === page}
                aria-current={pageNumber === page ? "page" : undefined}
                onClick={(event) =>
                  handlePageClick(event, pageNumber, onPageChange)
                }
              >
                {pageNumber}
              </PaginationLink>
            </PaginationItem>
          );
        })}
        <PaginationItem>
          <PaginationNext
            href="#"
            aria-label="Go to next page"
            aria-disabled={page >= lastPage}
            tabIndex={page >= lastPage ? -1 : undefined}
            onClick={(event) => {
              event.preventDefault();
              if (page < lastPage) {
                onPageChange(page + 1);
              }
            }}
          />
        </PaginationItem>
      </PaginationContent>
    </Pagination>
  );
}
