/**
 * More Navigator
 *
 * @package ICT_Platform_Mobile
 * @since   1.0.0
 */

import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { useTheme } from '../context/ThemeContext';

import { MoreMenuScreen } from '../screens/more/MoreMenuScreen';
import { SettingsScreen } from '../screens/more/SettingsScreen';
import { ProfileScreen } from '../screens/more/ProfileScreen';

import type { MoreStackParamList } from '../types';

const Stack = createNativeStackNavigator<MoreStackParamList>();

export const MoreNavigator: React.FC = () => {
  const { theme } = useTheme();

  return (
    <Stack.Navigator
      screenOptions={{
        headerStyle: {
          backgroundColor: theme.colors.background,
        },
        headerTintColor: theme.colors.text,
        headerShadowVisible: false,
        animation: 'slide_from_right',
      }}
    >
      <Stack.Screen
        name="MoreMenu"
        component={MoreMenuScreen}
        options={{ title: 'More' }}
      />
      <Stack.Screen
        name="Settings"
        component={SettingsScreen}
        options={{ title: 'Settings' }}
      />
      <Stack.Screen
        name="Profile"
        component={ProfileScreen}
        options={{ title: 'Profile' }}
      />
    </Stack.Navigator>
  );
};
