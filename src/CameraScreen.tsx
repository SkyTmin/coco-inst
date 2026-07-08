import Slider from '@react-native-community/slider';
import { CameraType, CameraView, useCameraPermissions } from 'expo-camera';
import * as ImagePicker from 'expo-image-picker';
import * as MediaLibrary from 'expo-media-library';
import React, { useRef, useState } from 'react';
import {
  Alert,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { GRID_LABELS, GRID_MODES, GridMode, GridOverlay } from './GridOverlay';
import { ReferenceOverlay } from './ReferenceOverlay';

export function CameraScreen() {
  const insets = useSafeAreaInsets();
  const cameraRef = useRef<CameraView>(null);

  const [cameraPermission, requestCameraPermission] = useCameraPermissions();
  const [facing, setFacing] = useState<CameraType>('back');
  const [gridMode, setGridMode] = useState<GridMode>('thirds');
  const [referenceUri, setReferenceUri] = useState<string | null>(null);
  const [referenceOpacity, setReferenceOpacity] = useState(0.4);
  const [capturing, setCapturing] = useState(false);

  if (!cameraPermission) {
    return <View style={styles.root} />;
  }

  if (!cameraPermission.granted) {
    return (
      <View style={[styles.root, styles.permissionContainer]}>
        <Text style={styles.permissionTitle}>Coco Camera</Text>
        <Text style={styles.permissionText}>
          Чтобы показывать направляющие композиции и делать снимки, приложению нужен доступ к
          камере.
        </Text>
        <Pressable style={styles.permissionButton} onPress={requestCameraPermission}>
          <Text style={styles.permissionButtonText}>Разрешить камеру</Text>
        </Pressable>
      </View>
    );
  }

  const cycleGrid = () => {
    const next = GRID_MODES[(GRID_MODES.indexOf(gridMode) + 1) % GRID_MODES.length];
    setGridMode(next);
  };

  const toggleFacing = () => {
    setFacing((current) => (current === 'back' ? 'front' : 'back'));
  };

  const pickReference = async () => {
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: 'images',
      quality: 1,
    });
    if (!result.canceled && result.assets.length > 0) {
      setReferenceUri(result.assets[0].uri);
    }
  };

  const takePhoto = async () => {
    if (!cameraRef.current || capturing) {
      return;
    }
    setCapturing(true);
    try {
      const photo = await cameraRef.current.takePictureAsync();
      if (photo) {
        const { granted } = await MediaLibrary.requestPermissionsAsync(true);
        if (granted) {
          await MediaLibrary.saveToLibraryAsync(photo.uri);
        } else {
          Alert.alert(
            'Снимок не сохранён',
            'Разрешите доступ к фото в настройках, чтобы сохранять снимки в галерею.'
          );
        }
      }
    } catch {
      Alert.alert('Ошибка', 'Не удалось сделать снимок. Попробуйте ещё раз.');
    } finally {
      setCapturing(false);
    }
  };

  return (
    <View style={styles.root}>
      <CameraView ref={cameraRef} style={StyleSheet.absoluteFill} facing={facing} />

      <GridOverlay mode={gridMode} />
      {referenceUri && <ReferenceOverlay uri={referenceUri} opacity={referenceOpacity} />}

      <View style={[styles.topBar, { paddingTop: insets.top + 8 }]}>
        <Pressable style={styles.toolButton} onPress={cycleGrid}>
          <Text style={styles.toolButtonText}>{GRID_LABELS[gridMode]}</Text>
        </Pressable>
        <Pressable style={styles.toolButton} onPress={toggleFacing}>
          <Text style={styles.toolButtonText}>Сменить камеру</Text>
        </Pressable>
      </View>

      <View style={[styles.bottomPanel, { paddingBottom: insets.bottom + 16 }]}>
        {referenceUri && (
          <View style={styles.opacityRow}>
            <Text style={styles.opacityLabel}>Эскиз</Text>
            <Slider
              style={styles.slider}
              minimumValue={0.05}
              maximumValue={0.9}
              value={referenceOpacity}
              onValueChange={setReferenceOpacity}
              minimumTrackTintColor="#FFFFFF"
              maximumTrackTintColor="rgba(255,255,255,0.3)"
              thumbTintColor="#FFFFFF"
            />
          </View>
        )}

        <View style={styles.controlsRow}>
          <Pressable
            style={styles.toolButton}
            onPress={referenceUri ? () => setReferenceUri(null) : pickReference}
          >
            <Text style={styles.toolButtonText}>
              {referenceUri ? 'Убрать эскиз' : 'Эскиз-портрет'}
            </Text>
          </Pressable>

          <Pressable
            style={[styles.shutter, capturing && styles.shutterDisabled]}
            onPress={takePhoto}
          >
            <View style={styles.shutterInner} />
          </Pressable>

          {/* Symmetric placeholder keeps the shutter centered. */}
          <View style={styles.toolButtonPlaceholder} />
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: '#000',
  },
  permissionContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    padding: 32,
    gap: 16,
  },
  permissionTitle: {
    color: '#FFF',
    fontSize: 28,
    fontWeight: '700',
  },
  permissionText: {
    color: 'rgba(255,255,255,0.8)',
    fontSize: 16,
    textAlign: 'center',
    lineHeight: 22,
  },
  permissionButton: {
    backgroundColor: '#FFF',
    borderRadius: 24,
    paddingHorizontal: 24,
    paddingVertical: 12,
  },
  permissionButtonText: {
    color: '#000',
    fontSize: 16,
    fontWeight: '600',
  },
  topBar: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
  },
  bottomPanel: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    paddingHorizontal: 16,
    gap: 12,
  },
  opacityRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: 'rgba(0,0,0,0.35)',
    borderRadius: 16,
    paddingHorizontal: 16,
    paddingVertical: 8,
  },
  opacityLabel: {
    color: '#FFF',
    fontSize: 14,
  },
  slider: {
    flex: 1,
    height: 32,
  },
  controlsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  toolButton: {
    backgroundColor: 'rgba(0,0,0,0.35)',
    borderRadius: 20,
    paddingHorizontal: 16,
    paddingVertical: 10,
    minWidth: 110,
    alignItems: 'center',
  },
  toolButtonPlaceholder: {
    minWidth: 110,
  },
  toolButtonText: {
    color: '#FFF',
    fontSize: 14,
    fontWeight: '500',
  },
  shutter: {
    width: 72,
    height: 72,
    borderRadius: 36,
    borderWidth: 4,
    borderColor: '#FFF',
    alignItems: 'center',
    justifyContent: 'center',
  },
  shutterDisabled: {
    opacity: 0.5,
  },
  shutterInner: {
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: '#FFF',
  },
});
