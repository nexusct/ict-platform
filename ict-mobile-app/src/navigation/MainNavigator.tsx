/**
 * Main Navigator (Tab Navigator)
 *
 * @package ICT_Platform_Mobile
 * @since   1.0.0
 */

import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { useTheme } from '../context/ThemeContext';
import { useSelector } from 'react-redux';
import type { RootState } from '../store';

// Screens
import { DashboardScreen } from '../screens/DashboardScreen';
import { ProjectsNavigator } from './ProjectsNavigator';
import { TimeTrackingScreen } from '../screens/TimeTrackingScreen';
import { InventoryNavigator } from './InventoryNavigator';
import { MoreNavigator } from './MoreNavigator';

import type { MainTabParamList } from '../types';

const Tab = createBottomTabNavigator<MainTabParamList>();

// Tab bar icons
const TabIcon = ({ name, focused, color }: { name: string; focused: boolean; color: string }) => {
  const icons: Record<string, string> = {
    Dashboard: '📊',
    Projects: '📁',
    TimeTracking: '⏱️',
    Inventory: '📦',
    More: '⚙️',
  };

  return (
    <View style={styles.tabIcon}>
      <Text style={{ fontSize: 24 }}>{icons[name]}</Text>
    </View>
  );
};

export const MainNavigator: React.FC = () => {
  const { theme } = useTheme();
  const { activeEntry } = useSelector((state: RootState) => state.timeEntries);
  const { unreadCount } = useSelector((state: RootState) => state.notifications);

  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        headerShown: false,
        tabBarIcon: ({ focused, color }) => (
          <TabIcon name={route.name} focused={focused} color={color} />
        ),
        tabBarActiveTintColor: theme.colors.primary,
        tabBarInactiveTintColor: theme.colors.textMuted,
        tabBarStyle: {
          backgroundColor: theme.colors.background,
          borderTopColor: theme.colors.border,
          height: 85,
          paddingBottom: 25,
          paddingTop: 10,
        },
        tabBarLabelStyle: {
          fontSize: 11,
          fontWeight: '500',
        },
      })}
    >
      <Tab.Screen
        name="Dashboard"
        component={DashboardScreen}
        options={{
          tabBarLabel: 'Dashboard',
        }}
      />
      <Tab.Screen
        name="Projects"
        component={ProjectsNavigator}
        options={{
          tabBarLabel: 'Projects',
        }}
      />
      <Tab.Screen
        name="TimeTracking"
        component={TimeTrackingScreen}
        options={{
          tabBarLabel: 'Time',
          tabBarBadge: activeEntry ? '●' : undefined,
          tabBarBadgeStyle: {
            backgroundColor: theme.colors.success,
            minWidth: 12,
            maxHeight: 12,
            fontSize: 8,
          },
        }}
      />
      <Tab.Screen
        name="Inventory"
        component={InventoryNavigator}
        options={{
          tabBarLabel: 'Inventory',
        }}
      />
      <Tab.Screen
        name="More"
        component={MoreNavigator}
        options={{
          tabBarLabel: 'More',
          tabBarBadge: unreadCount > 0 ? unreadCount : undefined,
        }}
      />
    </Tab.Navigator>
  );
};

const styles = StyleSheet.create({
  tabIcon: {
    alignItems: 'center',
    justifyContent: 'center',
  },
});
