/**
 * Pagination Component
 *
 * Accessible pagination with page size selection and jump to page
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useState, useCallback } from 'react';
import { Icon } from './Icon';

interface PaginationProps {
  currentPage: number;
  totalPages: number;
  totalItems: number;
  itemsPerPage: number;
  onPageChange: (page: number) => void;
  onPageSizeChange?: (size: number) => void;
  pageSizeOptions?: number[];
  showPageSize?: boolean;
  showItemCount?: boolean;
  showJumpToPage?: boolean;
  siblingCount?: number;
  className?: string;
}

export const Pagination: React.FC<PaginationProps> = ({
  currentPage,
  totalPages,
  totalItems,
  itemsPerPage,
  onPageChange,
  onPageSizeChange,
  pageSizeOptions = [10, 25, 50, 100],
  showPageSize = true,
  showItemCount = true,
  showJumpToPage = false,
  siblingCount = 1,
  className = '',
}) => {
  const [jumpValue, setJumpValue] = useState('');

  // Calculate the range of items being displayed
  const startItem = (currentPage - 1) * itemsPerPage + 1;
  const endItem = Math.min(currentPage * itemsPerPage, totalItems);

  // Generate page numbers to display
  const getPageNumbers = useCallback(() => {
    const pages: (number | 'ellipsis')[] = [];
    const totalNumbers = siblingCount * 2 + 3; // siblings + first + last + current

    if (totalPages <= totalNumbers + 2) {
      // Show all pages if total is small
      for (let i = 1; i <= totalPages; i++) {
        pages.push(i);
      }
    } else {
      const leftSiblingIndex = Math.max(currentPage - siblingCount, 1);
      const rightSiblingIndex = Math.min(currentPage + siblingCount, totalPages);

      const showLeftEllipsis = leftSiblingIndex > 2;
      const showRightEllipsis = rightSiblingIndex < totalPages - 1;

      if (!showLeftEllipsis && showRightEllipsis) {
        // Show pages from start
        for (let i = 1; i <= 3 + siblingCount * 2; i++) {
          pages.push(i);
        }
        pages.push('ellipsis');
        pages.push(totalPages);
      } else if (showLeftEllipsis && !showRightEllipsis) {
        // Show pages at end
        pages.push(1);
        pages.push('ellipsis');
        for (let i = totalPages - (2 + siblingCount * 2); i <= totalPages; i++) {
          pages.push(i);
        }
      } else {
        // Show pages in middle
        pages.push(1);
        pages.push('ellipsis');
        for (let i = leftSiblingIndex; i <= rightSiblingIndex; i++) {
          pages.push(i);
        }
        pages.push('ellipsis');
        pages.push(totalPages);
      }
    }

    return pages;
  }, [currentPage, totalPages, siblingCount]);

  const handleJumpToPage = (e: React.FormEvent) => {
    e.preventDefault();
    const page = parseInt(jumpValue, 10);
    if (page >= 1 && page <= totalPages) {
      onPageChange(page);
      setJumpValue('');
    }
  };

  if (totalPages <= 1 && !showItemCount) {
    return null;
  }

  return (
    <nav
      className={`ict-pagination ${className}`}
      aria-label="Pagination navigation"
      role="navigation"
    >
      <div className="ict-pagination__info">
        {showItemCount && totalItems > 0 && (
          <span className="ict-pagination__count" aria-live="polite">
            Showing {startItem}-{endItem} of {totalItems}
          </span>
        )}

        {showPageSize && onPageSizeChange && (
          <div className="ict-pagination__page-size">
            <label htmlFor="page-size" className="ict-pagination__page-size-label">
              Show:
            </label>
            <select
              id="page-size"
              value={itemsPerPage}
              onChange={(e) => onPageSizeChange(parseInt(e.target.value, 10))}
              className="ict-pagination__page-size-select"
            >
              {pageSizeOptions.map((size) => (
                <option key={size} value={size}>
                  {size}
                </option>
              ))}
            </select>
          </div>
        )}
      </div>

      {totalPages > 1 && (
        <div className="ict-pagination__controls">
          {/* Previous button */}
          <button
            onClick={() => onPageChange(currentPage - 1)}
            disabled={currentPage === 1}
            className="ict-pagination__button ict-pagination__button--prev"
            aria-label="Previous page"
          >
            <Icon name="chevron-left" size={16} aria-hidden={true} />
            <span className="ict-pagination__button-text">Previous</span>
          </button>

          {/* Page numbers */}
          <ul className="ict-pagination__pages" role="list">
            {getPageNumbers().map((page, index) => {
              if (page === 'ellipsis') {
                return (
                  <li key={`ellipsis-${index}`} className="ict-pagination__ellipsis">
                    <span aria-hidden={true}>&hellip;</span>
                  </li>
                );
              }

              const isCurrentPage = page === currentPage;

              return (
                <li key={page}>
                  <button
                    onClick={() => onPageChange(page)}
                    className={`ict-pagination__page ${isCurrentPage ? 'ict-pagination__page--current' : ''}`}
                    aria-current={isCurrentPage ? 'page' : undefined}
                    aria-label={`Page ${page}`}
                  >
                    {page}
                  </button>
                </li>
              );
            })}
          </ul>

          {/* Next button */}
          <button
            onClick={() => onPageChange(currentPage + 1)}
            disabled={currentPage === totalPages}
            className="ict-pagination__button ict-pagination__button--next"
            aria-label="Next page"
          >
            <span className="ict-pagination__button-text">Next</span>
            <Icon name="chevron-right" size={16} aria-hidden={true} />
          </button>

          {/* Jump to page */}
          {showJumpToPage && totalPages > 10 && (
            <form onSubmit={handleJumpToPage} className="ict-pagination__jump">
              <label htmlFor="jump-to-page" className="screen-reader-text">
                Jump to page
              </label>
              <input
                id="jump-to-page"
                type="number"
                min={1}
                max={totalPages}
                value={jumpValue}
                onChange={(e) => setJumpValue(e.target.value)}
                placeholder="Go to"
                className="ict-pagination__jump-input"
                aria-label="Jump to page number"
              />
              <button
                type="submit"
                className="ict-pagination__jump-button"
                aria-label="Go to page"
              >
                Go
              </button>
            </form>
          )}
        </div>
      )}
    </nav>
  );
};

// Simple pagination hook
interface UsePaginationOptions {
  totalItems: number;
  initialPage?: number;
  initialPageSize?: number;
}

export function usePagination({
  totalItems,
  initialPage = 1,
  initialPageSize = 10,
}: UsePaginationOptions) {
  const [currentPage, setCurrentPage] = useState(initialPage);
  const [pageSize, setPageSize] = useState(initialPageSize);

  const totalPages = Math.ceil(totalItems / pageSize);

  const goToPage = useCallback(
    (page: number) => {
      const validPage = Math.max(1, Math.min(page, totalPages));
      setCurrentPage(validPage);
    },
    [totalPages]
  );

  const nextPage = useCallback(() => {
    goToPage(currentPage + 1);
  }, [currentPage, goToPage]);

  const prevPage = useCallback(() => {
    goToPage(currentPage - 1);
  }, [currentPage, goToPage]);

  const changePageSize = useCallback(
    (newSize: number) => {
      setPageSize(newSize);
      // Reset to page 1 when changing page size
      setCurrentPage(1);
    },
    []
  );

  // Calculate offset for API calls
  const offset = (currentPage - 1) * pageSize;

  return {
    currentPage,
    pageSize,
    totalPages,
    offset,
    goToPage,
    nextPage,
    prevPage,
    changePageSize,
    isFirstPage: currentPage === 1,
    isLastPage: currentPage === totalPages,
  };
}

export default Pagination;
