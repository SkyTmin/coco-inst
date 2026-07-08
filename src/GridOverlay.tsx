import React from 'react';
import { StyleSheet, View } from 'react-native';

export type GridMode = 'none' | 'thirds' | 'golden' | 'center';

export const GRID_MODES: GridMode[] = ['none', 'thirds', 'golden', 'center'];

export const GRID_LABELS: Record<GridMode, string> = {
  none: 'Без сетки',
  thirds: 'Трети',
  golden: 'Золотое сечение',
  center: 'Центр',
};

const LINE_COLOR = 'rgba(255, 255, 255, 0.55)';
const LINE_WIDTH = StyleSheet.hairlineWidth * 2;

// Positions are fractions of the frame: 1/3-2/3 for thirds, 1/φ-based for golden ratio.
const RATIOS: Partial<Record<GridMode, [number, number]>> = {
  thirds: [1 / 3, 2 / 3],
  golden: [0.382, 0.618],
};

export function GridOverlay({ mode }: { mode: GridMode }) {
  if (mode === 'none') {
    return null;
  }

  if (mode === 'center') {
    return (
      <View pointerEvents="none" style={StyleSheet.absoluteFill}>
        <View style={[styles.vLine, { left: '50%' }]} />
        <View style={[styles.hLine, { top: '50%' }]} />
        <View style={styles.centerCircle} />
      </View>
    );
  }

  const [a, b] = RATIOS[mode]!;
  return (
    <View pointerEvents="none" style={StyleSheet.absoluteFill}>
      <View style={[styles.vLine, { left: `${a * 100}%` }]} />
      <View style={[styles.vLine, { left: `${b * 100}%` }]} />
      <View style={[styles.hLine, { top: `${a * 100}%` }]} />
      <View style={[styles.hLine, { top: `${b * 100}%` }]} />
    </View>
  );
}

const styles = StyleSheet.create({
  vLine: {
    position: 'absolute',
    top: 0,
    bottom: 0,
    width: LINE_WIDTH,
    backgroundColor: LINE_COLOR,
  },
  hLine: {
    position: 'absolute',
    left: 0,
    right: 0,
    height: LINE_WIDTH,
    backgroundColor: LINE_COLOR,
  },
  centerCircle: {
    position: 'absolute',
    top: '50%',
    left: '50%',
    width: 96,
    height: 96,
    marginTop: -48,
    marginLeft: -48,
    borderRadius: 48,
    borderWidth: LINE_WIDTH,
    borderColor: LINE_COLOR,
  },
});
