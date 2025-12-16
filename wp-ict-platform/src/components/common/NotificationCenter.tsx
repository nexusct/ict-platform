/**
 * Notification Center Component
 *
 * Centralized notification panel for system alerts and messages
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useState, useEffect, useCallback } from 'react';
import { Icon, IconName } from './Icon';

export interface Notification {
  id: string;
  type: 'info' | 'success' | 'warning' | 'error';
  title: string;
  message: string;
  timestamp: Date;
  read: boolean;
  action?: {
    label: string;
    onClick: () => void;
  };
  dismissible?: boolean;
}

interface NotificationCenterProps {
  notifications: Notification[];
  onMarkAsRead: (id: string) => void;
  onDismiss: (id: string) => void;
  onClearAll: () => void;
  maxVisible?: number;
}

export const NotificationCenter: React.FC<NotificationCenterProps> = ({
  notifications,
  onMarkAsRead,
  onDismiss,
  onClearAll,
  maxVisible = 10,
}) => {
  const [isOpen, setIsOpen] = useState(false);

  const unreadCount = notifications.filter((n) => !n.read).length;
  const visibleNotifications = notifications.slice(0, maxVisible);

  const handleToggle = () => {
    setIsOpen(!isOpen);
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Escape') {
      setIsOpen(false);
    }
  };

  const getIcon = (type: Notification['type']): IconName => {
    const iconMap: Record<string, IconName> = {
      info: 'info',
      success: 'check-circle',
      warning: 'alert-triangle',
      error: 'alert-circle',
    };
    return iconMap[type];
  };

  const formatTime = (date: Date): string => {
    const now = new Date();
    const diff = now.getTime() - date.getTime();
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);

    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    if (hours < 24) return `${hours}h ago`;
    if (days < 7) return `${days}d ago`;
    return date.toLocaleDateString();
  };

  return (
    <div className="ict-notification-center" onKeyDown={handleKeyDown}>
      <button
        className="ict-notification-center__trigger"
        onClick={handleToggle}
        aria-expanded={isOpen}
        aria-haspopup="true"
        aria-label={`Notifications${unreadCount > 0 ? `, ${unreadCount} unread` : ''}`}
      >
        <Icon name="info" size={20} />
        {unreadCount > 0 && (
          <span className="ict-notification-center__badge" aria-hidden="true">
            {unreadCount > 99 ? '99+' : unreadCount}
          </span>
        )}
      </button>

      {isOpen && (
        <>
          <div
            className="ict-notification-center__overlay"
            onClick={() => setIsOpen(false)}
            aria-hidden="true"
          />
          <div
            className="ict-notification-center__panel"
            role="dialog"
            aria-label="Notifications"
          >
            <div className="ict-notification-center__header">
              <h3 className="ict-notification-center__title">Notifications</h3>
              {notifications.length > 0 && (
                <button
                  onClick={onClearAll}
                  className="ict-notification-center__clear"
                  type="button"
                >
                  Clear all
                </button>
              )}
            </div>

            <div className="ict-notification-center__list" role="list">
              {visibleNotifications.length === 0 ? (
                <div className="ict-notification-center__empty">
                  <Icon name="check-circle" size={32} />
                  <p>No notifications</p>
                </div>
              ) : (
                visibleNotifications.map((notification) => (
                  <div
                    key={notification.id}
                    className={`ict-notification-center__item ict-notification-center__item--${notification.type} ${!notification.read ? 'ict-notification-center__item--unread' : ''}`}
                    role="listitem"
                    onClick={() => onMarkAsRead(notification.id)}
                  >
                    <div className="ict-notification-center__item-icon">
                      <Icon name={getIcon(notification.type)} size={18} />
                    </div>
                    <div className="ict-notification-center__item-content">
                      <p className="ict-notification-center__item-title">
                        {notification.title}
                      </p>
                      <p className="ict-notification-center__item-message">
                        {notification.message}
                      </p>
                      {notification.action && (
                        <button
                          onClick={(e) => {
                            e.stopPropagation();
                            notification.action?.onClick();
                          }}
                          className="ict-notification-center__item-action"
                          type="button"
                        >
                          {notification.action.label}
                        </button>
                      )}
                      <span className="ict-notification-center__item-time">
                        {formatTime(notification.timestamp)}
                      </span>
                    </div>
                    {notification.dismissible !== false && (
                      <button
                        onClick={(e) => {
                          e.stopPropagation();
                          onDismiss(notification.id);
                        }}
                        className="ict-notification-center__item-dismiss"
                        aria-label="Dismiss notification"
                        type="button"
                      >
                        <Icon name="x" size={14} />
                      </button>
                    )}
                  </div>
                ))
              )}
            </div>

            {notifications.length > maxVisible && (
              <div className="ict-notification-center__footer">
                <span>
                  Showing {maxVisible} of {notifications.length} notifications
                </span>
              </div>
            )}
          </div>
        </>
      )}
    </div>
  );
};

// Hook for managing notifications
export function useNotifications(maxNotifications = 50) {
  const [notifications, setNotifications] = useState<Notification[]>([]);

  const addNotification = useCallback(
    (notification: Omit<Notification, 'id' | 'timestamp' | 'read'>) => {
      const newNotification: Notification = {
        ...notification,
        id: `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
        timestamp: new Date(),
        read: false,
      };

      setNotifications((prev) => {
        const updated = [newNotification, ...prev];
        return updated.slice(0, maxNotifications);
      });

      return newNotification.id;
    },
    [maxNotifications]
  );

  const markAsRead = useCallback((id: string) => {
    setNotifications((prev) =>
      prev.map((n) => (n.id === id ? { ...n, read: true } : n))
    );
  }, []);

  const markAllAsRead = useCallback(() => {
    setNotifications((prev) => prev.map((n) => ({ ...n, read: true })));
  }, []);

  const dismiss = useCallback((id: string) => {
    setNotifications((prev) => prev.filter((n) => n.id !== id));
  }, []);

  const clearAll = useCallback(() => {
    setNotifications([]);
  }, []);

  // Helper methods
  const notifySuccess = useCallback(
    (title: string, message: string) => {
      return addNotification({ type: 'success', title, message });
    },
    [addNotification]
  );

  const notifyError = useCallback(
    (title: string, message: string) => {
      return addNotification({ type: 'error', title, message });
    },
    [addNotification]
  );

  const notifyWarning = useCallback(
    (title: string, message: string) => {
      return addNotification({ type: 'warning', title, message });
    },
    [addNotification]
  );

  const notifyInfo = useCallback(
    (title: string, message: string) => {
      return addNotification({ type: 'info', title, message });
    },
    [addNotification]
  );

  return {
    notifications,
    addNotification,
    markAsRead,
    markAllAsRead,
    dismiss,
    clearAll,
    notifySuccess,
    notifyError,
    notifyWarning,
    notifyInfo,
    unreadCount: notifications.filter((n) => !n.read).length,
  };
}

export default NotificationCenter;
