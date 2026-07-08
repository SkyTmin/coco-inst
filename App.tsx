import { StatusBar } from 'expo-status-bar';
import React from 'react';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { CameraScreen } from './src/CameraScreen';

export default function App() {
  return (
    <SafeAreaProvider>
      <StatusBar style="light" />
      <CameraScreen />
    </SafeAreaProvider>
  );
}
