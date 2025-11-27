/**
 * Projects Navigator
 *
 * @package ICT_Platform_Mobile
 * @since   1.0.0
 */

import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { useTheme } from '../context/ThemeContext';

import { ProjectListScreen } from '../screens/projects/ProjectListScreen';
import { ProjectDetailScreen } from '../screens/projects/ProjectDetailScreen';

import type { ProjectStackParamList } from '../types';

const Stack = createNativeStackNavigator<ProjectStackParamList>();

export const ProjectsNavigator: React.FC = () => {
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
        name="ProjectList"
        component={ProjectListScreen}
        options={{ title: 'Projects' }}
      />
      <Stack.Screen
        name="ProjectDetail"
        component={ProjectDetailScreen}
        options={{ title: 'Project Details' }}
      />
    </Stack.Navigator>
  );
};
