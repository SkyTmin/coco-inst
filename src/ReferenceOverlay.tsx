import React from 'react';
import { Image, StyleSheet, View } from 'react-native';

/**
 * Полупрозрачный портрет-эскиз поверх видоискателя: пользователь совмещает
 * человека в кадре с референсом и получает нужную позу/композицию.
 */
export function ReferenceOverlay({ uri, opacity }: { uri: string; opacity: number }) {
  return (
    <View pointerEvents="none" style={StyleSheet.absoluteFill}>
      <Image source={{ uri }} style={[styles.image, { opacity }]} resizeMode="contain" />
    </View>
  );
}

const styles = StyleSheet.create({
  image: {
    flex: 1,
    width: '100%',
  },
});
