/**
 * Confirm Dialog Component
 *
 * Accessible confirmation dialog to replace native confirm()
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useEffect, useRef, useCallback, useState } from 'react';
import { createPortal } from 'react-dom';
import { Icon } from './Icon';

interface ConfirmDialogProps {
  isOpen: boolean;
  title: string;
  message: string;
  confirmLabel?: string;
  cancelLabel?: string;
  variant?: 'danger' | 'warning' | 'info';
  onConfirm: () => void | Promise<void>;
  onCancel: () => void;
  loading?: boolean;
}

export const ConfirmDialog: React.FC<ConfirmDialogProps> = ({
  isOpen,
  title,
  message,
  confirmLabel = 'Confirm',
  cancelLabel = 'Cancel',
  variant = 'danger',
  onConfirm,
  onCancel,
  loading = false,
}) => {
  const dialogRef = useRef<HTMLDivElement>(null);
  const confirmButtonRef = useRef<HTMLButtonElement>(null);
  const [isProcessing, setIsProcessing] = useState(false);

  // Focus trap
  useEffect(() => {
    if (isOpen && dialogRef.current) {
      const focusableElements = dialogRef.current.querySelectorAll<HTMLElement>(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );
      const firstElement = focusableElements[0];
      const lastElement = focusableElements[focusableElements.length - 1];

      const handleTab = (e: KeyboardEvent) => {
        if (e.key === 'Tab') {
          if (e.shiftKey) {
            if (document.activeElement === firstElement) {
              e.preventDefault();
              lastElement?.focus();
            }
          } else {
            if (document.activeElement === lastElement) {
              e.preventDefault();
              firstElement?.focus();
            }
          }
        }
      };

      document.addEventListener('keydown', handleTab);
      confirmButtonRef.current?.focus();

      return () => document.removeEventListener('keydown', handleTab);
    }
  }, [isOpen]);

  // Handle escape key
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isOpen && !isProcessing) {
        onCancel();
      }
    };

    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, [isOpen, isProcessing, onCancel]);

  // Prevent body scroll when open
  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
      return () => {
        document.body.style.overflow = '';
      };
    }
  }, [isOpen]);

  const handleConfirm = useCallback(async () => {
    setIsProcessing(true);
    try {
      await onConfirm();
    } finally {
      setIsProcessing(false);
    }
  }, [onConfirm]);

  const handleOverlayClick = (e: React.MouseEvent) => {
    if (e.target === e.currentTarget && !isProcessing) {
      onCancel();
    }
  };

  if (!isOpen) return null;

  const getIcon = () => {
    switch (variant) {
      case 'danger':
        return 'alert-circle';
      case 'warning':
        return 'alert-triangle';
      case 'info':
      default:
        return 'info';
    }
  };

  const content = (
    <div
      className="ict-confirm-overlay"
      onClick={handleOverlayClick}
      role="presentation"
    >
      <div
        ref={dialogRef}
        className={`ict-confirm-dialog ict-confirm-dialog--${variant}`}
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="confirm-title"
        aria-describedby="confirm-message"
      >
        <div className="ict-confirm-dialog__header">
          <div className={`ict-confirm-dialog__icon ict-confirm-dialog__icon--${variant}`} aria-hidden="true">
            <Icon name={getIcon()} size={24} />
          </div>
          <h3 id="confirm-title" className="ict-confirm-dialog__title">
            {title}
          </h3>
        </div>

        <div className="ict-confirm-dialog__body">
          <p id="confirm-message" className="ict-confirm-dialog__message">
            {message}
          </p>
        </div>

        <div className="ict-confirm-dialog__footer">
          <button
            onClick={onCancel}
            className="ict-button ict-button--secondary"
            type="button"
            disabled={isProcessing || loading}
          >
            {cancelLabel}
          </button>
          <button
            ref={confirmButtonRef}
            onClick={handleConfirm}
            className={`ict-button ict-button--${variant === 'danger' ? 'danger' : 'primary'}`}
            type="button"
            disabled={isProcessing || loading}
          >
            {isProcessing || loading ? (
              <>
                <span className="ict-spinner-inline" aria-hidden="true" />
                Processing...
              </>
            ) : (
              confirmLabel
            )}
          </button>
        </div>
      </div>
    </div>
  );

  return createPortal(content, document.body);
};

// Hook for easier confirm dialog usage
interface UseConfirmOptions {
  title: string;
  message: string;
  confirmLabel?: string;
  cancelLabel?: string;
  variant?: 'danger' | 'warning' | 'info';
}

interface UseConfirmReturn {
  isOpen: boolean;
  confirm: () => Promise<boolean>;
  ConfirmDialogComponent: React.FC;
}

export const useConfirm = (options: UseConfirmOptions): UseConfirmReturn => {
  const [isOpen, setIsOpen] = useState(false);
  const [resolvePromise, setResolvePromise] = useState<((value: boolean) => void) | null>(null);

  const confirm = useCallback(() => {
    setIsOpen(true);
    return new Promise<boolean>((resolve) => {
      setResolvePromise(() => resolve);
    });
  }, []);

  const handleConfirm = useCallback(() => {
    setIsOpen(false);
    resolvePromise?.(true);
  }, [resolvePromise]);

  const handleCancel = useCallback(() => {
    setIsOpen(false);
    resolvePromise?.(false);
  }, [resolvePromise]);

  const ConfirmDialogComponent: React.FC = useCallback(
    () => (
      <ConfirmDialog
        isOpen={isOpen}
        title={options.title}
        message={options.message}
        confirmLabel={options.confirmLabel}
        cancelLabel={options.cancelLabel}
        variant={options.variant}
        onConfirm={handleConfirm}
        onCancel={handleCancel}
      />
    ),
    [isOpen, options, handleConfirm, handleCancel]
  );

  return { isOpen, confirm, ConfirmDialogComponent };
};

export default ConfirmDialog;
