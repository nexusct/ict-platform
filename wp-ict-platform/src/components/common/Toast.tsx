/**
 * Toast Notification Component
 *
 * Provides accessible toast notifications with auto-dismiss and actions
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useEffect, useCallback, useState } from 'react';
import { useAppDispatch, useAppSelector } from '../../hooks/useAppDispatch';
import { hideToast } from '../../store/slices/uiSlice';
import { Icon } from './Icon';

interface ToastProps {
  position?: 'top-right' | 'top-left' | 'bottom-right' | 'bottom-left' | 'top-center' | 'bottom-center';
  maxToasts?: number;
}

export const ToastContainer: React.FC<ToastProps> = ({
  position = 'top-right',
}) => {
  const dispatch = useAppDispatch();
  const toast = useAppSelector((state) => state.ui.toast);
  const [isVisible, setIsVisible] = useState(false);
  const [isExiting, setIsExiting] = useState(false);

  const dismissToast = useCallback(() => {
    setIsExiting(true);
    setTimeout(() => {
      setIsVisible(false);
      setIsExiting(false);
      dispatch(hideToast());
    }, 200);
  }, [dispatch]);

  useEffect(() => {
    if (!toast) {
      return;
    }

    setIsVisible(true);
    setIsExiting(false);

    const duration = toast.duration || 5000;
    const timer = setTimeout(dismissToast, duration);

    return () => clearTimeout(timer);
  }, [toast, dismissToast]);

  if (!toast || !isVisible) {
    return null;
  }

  const getIcon = () => {
    switch (toast.type) {
      case 'success':
        return 'check-circle';
      case 'error':
        return 'alert-circle';
      case 'warning':
        return 'alert-triangle';
      case 'info':
      default:
        return 'info';
    }
  };

  return (
    <div
      className={`ict-toast-container ict-toast-container--${position}`}
      role="region"
      aria-label="Notifications"
    >
      <div
        className={`ict-toast ict-toast--${toast.type} ${isExiting ? 'ict-toast--exiting' : ''}`}
        role="alert"
        aria-live={toast.type === 'error' ? 'assertive' : 'polite'}
        aria-atomic="true"
      >
        <div className="ict-toast__icon" aria-hidden="true">
          <Icon name={getIcon()} size={20} />
        </div>
        <div className="ict-toast__content">
          <p className="ict-toast__message">{toast.message}</p>
        </div>
        <button
          onClick={dismissToast}
          className="ict-toast__close"
          aria-label="Dismiss notification"
          type="button"
        >
          <Icon name="x" size={16} />
        </button>
      </div>
    </div>
  );
};

// Standalone toast function for use outside Redux
interface ToastOptions {
  type: 'success' | 'error' | 'warning' | 'info';
  message: string;
  duration?: number;
  action?: {
    label: string;
    onClick: () => void;
  };
}

class ToastManager {
  private listeners: Set<(toast: ToastOptions) => void> = new Set();

  subscribe(listener: (toast: ToastOptions) => void) {
    this.listeners.add(listener);
    return () => this.listeners.delete(listener);
  }

  show(options: ToastOptions) {
    this.listeners.forEach((listener) => listener(options));
  }

  success(message: string, duration?: number) {
    this.show({ type: 'success', message, duration });
  }

  error(message: string, duration?: number) {
    this.show({ type: 'error', message, duration: duration || 8000 });
  }

  warning(message: string, duration?: number) {
    this.show({ type: 'warning', message, duration: duration || 6000 });
  }

  info(message: string, duration?: number) {
    this.show({ type: 'info', message, duration });
  }
}

export const toast = new ToastManager();

export default ToastContainer;
