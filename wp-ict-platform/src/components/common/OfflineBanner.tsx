/**
 * Offline Banner Component
 *
 * Displays a banner when the user is offline
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useState, useEffect } from 'react';
import { useIsOnline } from '../../hooks/useOnlineStatus';
import { Icon } from './Icon';

interface OfflineBannerProps {
  position?: 'top' | 'bottom';
  showReconnected?: boolean;
  reconnectedDuration?: number;
}

export const OfflineBanner: React.FC<OfflineBannerProps> = ({
  position = 'top',
  showReconnected = true,
  reconnectedDuration = 3000,
}) => {
  const isOnline = useIsOnline();
  const [wasOffline, setWasOffline] = useState(false);
  const [showReconnectedMessage, setShowReconnectedMessage] = useState(false);

  useEffect(() => {
    if (!isOnline) {
      setWasOffline(true);
      setShowReconnectedMessage(false);
    } else if (wasOffline && showReconnected) {
      setShowReconnectedMessage(true);
      const timer = setTimeout(() => {
        setShowReconnectedMessage(false);
        setWasOffline(false);
      }, reconnectedDuration);
      return () => clearTimeout(timer);
    }
  }, [isOnline, wasOffline, showReconnected, reconnectedDuration]);

  if (isOnline && !showReconnectedMessage) {
    return null;
  }

  return (
    <div
      className={`ict-offline-banner ict-offline-banner--${position} ${showReconnectedMessage ? 'ict-offline-banner--reconnected' : 'ict-offline-banner--offline'}`}
      role="alert"
      aria-live="assertive"
    >
      <div className="ict-offline-banner__content">
        <Icon
          name={showReconnectedMessage ? 'check-circle' : 'wifi-off'}
          size={18}
          aria-hidden={true}
        />
        <span className="ict-offline-banner__message">
          {showReconnectedMessage
            ? 'Connection restored'
            : 'You are currently offline. Some features may be unavailable.'}
        </span>
        {!isOnline && (
          <button
            onClick={() => window.location.reload()}
            className="ict-offline-banner__retry"
            type="button"
          >
            Retry
          </button>
        )}
      </div>
    </div>
  );
};

export default OfflineBanner;
