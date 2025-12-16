/**
 * Theme Context
 *
 * @package ICT_Platform_Mobile
 * @since   1.0.0
 */

import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { useColorScheme } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { DefaultTheme, DarkTheme, Theme as NavigationTheme } from '@react-navigation/native';

type ThemeMode = 'light' | 'dark' | 'system';

interface Theme {
  dark: boolean;
  colors: {
    primary: string;
    primaryLight: string;
    background: string;
    card: string;
    text: string;
    textSecondary: string;
    textMuted: string;
    border: string;
    borderLight: string;
    notification: string;
    success: string;
    warning: string;
    danger: string;
    accent: string;
  };
  navigation: NavigationTheme;
}

interface ThemeContextType {
  theme: Theme;
  themeMode: ThemeMode;
  setThemeMode: (mode: ThemeMode) => void;
  toggleTheme: () => void;
}

const lightTheme: Theme = {
  dark: false,
  colors: {
    primary: '#3b82f6',
    primaryLight: 'rgba(59, 130, 246, 0.1)',
    background: '#ffffff',
    card: '#f9fafb',
    text: '#1f2937',
    textSecondary: '#4b5563',
    textMuted: '#9ca3af',
    border: '#e5e7eb',
    borderLight: '#f3f4f6',
    notification: '#ef4444',
    success: '#10b981',
    warning: '#f59e0b',
    danger: '#ef4444',
    accent: '#8b5cf6',
  },
  navigation: {
    ...DefaultTheme,
    colors: {
      ...DefaultTheme.colors,
      primary: '#3b82f6',
      background: '#ffffff',
      card: '#ffffff',
      text: '#1f2937',
      border: '#e5e7eb',
      notification: '#ef4444',
    },
  },
};

const darkTheme: Theme = {
  dark: true,
  colors: {
    primary: '#60a5fa',
    primaryLight: 'rgba(96, 165, 250, 0.15)',
    background: '#111827',
    card: '#1f2937',
    text: '#f9fafb',
    textSecondary: '#d1d5db',
    textMuted: '#6b7280',
    border: '#374151',
    borderLight: '#1f2937',
    notification: '#ef4444',
    success: '#34d399',
    warning: '#fbbf24',
    danger: '#f87171',
    accent: '#a78bfa',
  },
  navigation: {
    ...DarkTheme,
    colors: {
      ...DarkTheme.colors,
      primary: '#60a5fa',
      background: '#111827',
      card: '#1f2937',
      text: '#f9fafb',
      border: '#374151',
      notification: '#ef4444',
    },
  },
};

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

export const ThemeProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const systemColorScheme = useColorScheme();
  const [themeMode, setThemeModeState] = useState<ThemeMode>('system');

  // Load saved theme preference
  useEffect(() => {
    AsyncStorage.getItem('theme_mode').then((saved) => {
      if (saved) {
        setThemeModeState(saved as ThemeMode);
      }
    });
  }, []);

  const setThemeMode = useCallback(async (mode: ThemeMode) => {
    setThemeModeState(mode);
    await AsyncStorage.setItem('theme_mode', mode);
  }, []);

  const toggleTheme = useCallback(() => {
    const nextMode: ThemeMode = themeMode === 'dark' ? 'light' : 'dark';
    setThemeMode(nextMode);
  }, [themeMode, setThemeMode]);

  // Determine effective theme
  const effectiveTheme = (() => {
    if (themeMode === 'system') {
      return systemColorScheme === 'dark' ? darkTheme : lightTheme;
    }
    return themeMode === 'dark' ? darkTheme : lightTheme;
  })();

  return (
    <ThemeContext.Provider value={{ theme: effectiveTheme, themeMode, setThemeMode, toggleTheme }}>
      {children}
    </ThemeContext.Provider>
  );
};

export const useTheme = () => {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error('useTheme must be used within ThemeProvider');
  }
  return context;
};
