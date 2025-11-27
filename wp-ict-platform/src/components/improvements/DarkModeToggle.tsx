/**
 * Dark Mode Toggle & Theme Provider
 *
 * Handles theme switching and persistence.
 *
 * @package ICT_Platform
 * @since   2.1.0
 */

import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import type { ThemeSettings } from '../../types';

interface ThemeContextType {
  theme: ThemeSettings;
  isDark: boolean;
  setTheme: (theme: Partial<ThemeSettings>) => void;
  toggleDarkMode: () => void;
}

const defaultTheme: ThemeSettings = {
  mode: 'system',
  fontSize: 'medium',
  compactMode: false,
};

const ThemeContext = createContext<ThemeContextType | null>(null);

export const useTheme = () => {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error('useTheme must be used within ThemeProvider');
  }
  return context;
};

interface ThemeProviderProps {
  children: React.ReactNode;
  storageKey?: string;
}

export const ThemeProvider: React.FC<ThemeProviderProps> = ({
  children,
  storageKey = 'ict_theme_settings',
}) => {
  const [theme, setThemeState] = useState<ThemeSettings>(() => {
    const saved = localStorage.getItem(storageKey);
    return saved ? { ...defaultTheme, ...JSON.parse(saved) } : defaultTheme;
  });

  const [systemDark, setSystemDark] = useState(
    window.matchMedia('(prefers-color-scheme: dark)').matches
  );

  // Listen for system theme changes
  useEffect(() => {
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    const handler = (e: MediaQueryListEvent) => setSystemDark(e.matches);
    mediaQuery.addEventListener('change', handler);
    return () => mediaQuery.removeEventListener('change', handler);
  }, []);

  // Calculate effective dark mode
  const isDark = theme.mode === 'dark' || (theme.mode === 'system' && systemDark);

  // Apply theme to document
  useEffect(() => {
    const root = document.documentElement;

    // Dark mode class
    if (isDark) {
      root.classList.add('ict-dark');
      root.style.setProperty('color-scheme', 'dark');
    } else {
      root.classList.remove('ict-dark');
      root.style.setProperty('color-scheme', 'light');
    }

    // Font size
    const fontSizes = { small: '14px', medium: '16px', large: '18px' };
    root.style.setProperty('--ict-base-font-size', fontSizes[theme.fontSize]);

    // Compact mode
    if (theme.compactMode) {
      root.classList.add('ict-compact');
    } else {
      root.classList.remove('ict-compact');
    }

    // Custom colors
    if (theme.primaryColor) {
      root.style.setProperty('--ict-primary', theme.primaryColor);
    }
    if (theme.accentColor) {
      root.style.setProperty('--ict-accent', theme.accentColor);
    }
  }, [theme, isDark]);

  // Save to localStorage
  useEffect(() => {
    localStorage.setItem(storageKey, JSON.stringify(theme));
  }, [theme, storageKey]);

  const setTheme = useCallback((partial: Partial<ThemeSettings>) => {
    setThemeState((prev) => ({ ...prev, ...partial }));
  }, []);

  const toggleDarkMode = useCallback(() => {
    setThemeState((prev) => ({
      ...prev,
      mode: prev.mode === 'dark' ? 'light' : 'dark',
    }));
  }, []);

  return (
    <ThemeContext.Provider value={{ theme, isDark, setTheme, toggleDarkMode }}>
      {children}
      <ThemeStyles />
    </ThemeContext.Provider>
  );
};

// Theme CSS variables
const ThemeStyles: React.FC = () => (
  <style>{`
    :root {
      /* Light theme (default) */
      --ict-bg-color: #ffffff;
      --ict-bg-secondary: #f9fafb;
      --ict-bg-hover: #f3f4f6;
      --ict-bg-weekend: #f9fafb;
      --ict-text-color: #1f2937;
      --ict-text-secondary: #4b5563;
      --ict-text-muted: #9ca3af;
      --ict-border-color: #e5e7eb;
      --ict-border-light: #f3f4f6;
      --ict-primary: #3b82f6;
      --ict-primary-hover: #2563eb;
      --ict-accent: #8b5cf6;
      --ict-success: #10b981;
      --ict-warning: #f59e0b;
      --ict-danger: #ef4444;
    }

    .ict-dark {
      --ict-bg-color: #1f2937;
      --ict-bg-secondary: #111827;
      --ict-bg-hover: #374151;
      --ict-bg-weekend: #111827;
      --ict-text-color: #f9fafb;
      --ict-text-secondary: #d1d5db;
      --ict-text-muted: #6b7280;
      --ict-border-color: #374151;
      --ict-border-light: #1f2937;
      --ict-primary: #60a5fa;
      --ict-primary-hover: #3b82f6;
    }

    .ict-compact {
      --ict-spacing-xs: 4px;
      --ict-spacing-sm: 6px;
      --ict-spacing-md: 10px;
      --ict-spacing-lg: 14px;
    }
  `}</style>
);

// Toggle button component
interface DarkModeToggleProps {
  size?: 'small' | 'medium' | 'large';
  showLabel?: boolean;
}

const DarkModeToggle: React.FC<DarkModeToggleProps> = ({
  size = 'medium',
  showLabel = false,
}) => {
  const { isDark, theme, setTheme } = useTheme();

  const sizes = {
    small: { toggle: 40, icon: 14 },
    medium: { toggle: 50, icon: 18 },
    large: { toggle: 60, icon: 22 },
  };

  const s = sizes[size];

  const handleClick = () => {
    const modes: ThemeSettings['mode'][] = ['light', 'dark', 'system'];
    const currentIndex = modes.indexOf(theme.mode);
    const nextIndex = (currentIndex + 1) % modes.length;
    setTheme({ mode: modes[nextIndex] });
  };

  const getIcon = () => {
    if (theme.mode === 'system') {
      return (
        <svg width={s.icon} height={s.icon} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <rect x="2" y="3" width="20" height="14" rx="2" />
          <path d="M8 21h8M12 17v4" />
        </svg>
      );
    }
    if (isDark) {
      return (
        <svg width={s.icon} height={s.icon} viewBox="0 0 24 24" fill="currentColor">
          <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
        </svg>
      );
    }
    return (
      <svg width={s.icon} height={s.icon} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
        <circle cx="12" cy="12" r="5" />
        <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" />
      </svg>
    );
  };

  const getLabel = () => {
    const labels = { light: 'Light', dark: 'Dark', system: 'System' };
    return labels[theme.mode];
  };

  return (
    <button
      className="ict-dark-mode-toggle"
      onClick={handleClick}
      title={`Theme: ${getLabel()}`}
      style={{ width: s.toggle, height: s.toggle / 2 + 8 }}
    >
      <span className="ict-toggle-icon">{getIcon()}</span>
      {showLabel && <span className="ict-toggle-label">{getLabel()}</span>}

      <style>{`
        .ict-dark-mode-toggle {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          gap: 6px;
          padding: 6px 12px;
          background: var(--ict-bg-secondary);
          border: 1px solid var(--ict-border-color);
          border-radius: 20px;
          color: var(--ict-text-color);
          cursor: pointer;
          transition: all 0.2s;
        }

        .ict-dark-mode-toggle:hover {
          background: var(--ict-bg-hover);
          border-color: var(--ict-primary);
        }

        .ict-toggle-icon {
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .ict-toggle-label {
          font-size: 12px;
          font-weight: 500;
        }
      `}</style>
    </button>
  );
};

export default DarkModeToggle;
