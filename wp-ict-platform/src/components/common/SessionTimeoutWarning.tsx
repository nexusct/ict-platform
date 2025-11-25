/**
 * Session Timeout Warning Component
 *
 * Displays a warning modal when session is about to expire
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React from 'react';
import { useSessionTimeout, formatRemainingTime } from '../../hooks/useSessionTimeout';
import { Icon } from './Icon';

interface SessionTimeoutWarningProps {
  timeout?: number;
  warningTime?: number;
  onLogout?: () => void;
  onExtend?: () => void;
}

export const SessionTimeoutWarning: React.FC<SessionTimeoutWarningProps> = ({
  timeout = 30 * 60 * 1000, // 30 minutes
  warningTime = 5 * 60 * 1000, // 5 minutes warning
  onLogout,
  onExtend,
}) => {
  const { isWarningShown, remainingTime, extendSession } = useSessionTimeout({
    timeout,
    warningTime,
    onTimeout: () => {
      onLogout?.();
      // Redirect to login page
      window.location.href = '/wp-login.php?loggedout=true';
    },
  });

  const handleExtend = () => {
    extendSession();
    onExtend?.();
  };

  const handleLogout = () => {
    onLogout?.();
    window.location.href = '/wp-login.php?action=logout';
  };

  if (!isWarningShown) {
    return null;
  }

  return (
    <div className="ict-session-warning" role="alertdialog" aria-labelledby="session-warning-title">
      <div className="ict-session-warning__overlay" aria-hidden="true" />
      <div className="ict-session-warning__dialog">
        <div className="ict-session-warning__icon">
          <Icon name="clock" size={48} />
        </div>
        <h2 id="session-warning-title" className="ict-session-warning__title">
          Session Expiring Soon
        </h2>
        <p className="ict-session-warning__message">
          Your session will expire in{' '}
          <strong className="ict-session-warning__time">
            {formatRemainingTime(remainingTime)}
          </strong>
        </p>
        <p className="ict-session-warning__description">
          Would you like to continue working or log out now?
        </p>
        <div className="ict-session-warning__actions">
          <button
            onClick={handleLogout}
            className="ict-button ict-button--secondary"
            type="button"
          >
            Log Out
          </button>
          <button
            onClick={handleExtend}
            className="ict-button ict-button--primary"
            type="button"
            autoFocus
          >
            Continue Session
          </button>
        </div>
      </div>
    </div>
  );
};

export default SessionTimeoutWarning;
