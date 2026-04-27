import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

const TABS = [
  { name: 'Home',      lib: 'feather', icon: 'home',        label: 'Home' },
  { name: 'MyQrCode',  lib: 'mci',     icon: 'qrcode',      label: 'My QR' },
  { name: 'ScanQrPay', lib: 'mci',     icon: 'qrcode-scan', label: 'Scan QR' },
  { name: 'Bills',     lib: 'feather', icon: 'file-text',   label: 'Bills' },
  { name: 'Profile',   lib: 'feather', icon: 'user',        label: 'Profile' },
];

function TabIcon({ tab, color }) {
  if (tab.lib === 'mci') {
    return <MaterialCommunityIcons name={tab.icon} size={20} color={color} />;
  }
  return <Feather name={tab.icon} size={20} color={color} />;
}

export default function BottomTabsOverlay({ navigation, active, onPressTab }) {
  const insets = useSafeAreaInsets();
  return (
    <View style={[styles.wrap, { paddingBottom: Math.max(insets.bottom, 10) }]}>
      {TABS.map((t) => {
        const isActive = active === t.name;
        const color = isActive ? '#111827' : '#9CA3AF';
        return (
          <TouchableOpacity
            key={t.name}
            style={styles.tab}
            activeOpacity={0.75}
            onPress={() => {
              onPressTab?.(t.name);
              // We're inside a Stack screen (e.g. NearbyProducts). Tabs live under:
              // Stack `Main` -> Drawer `MainTabs` -> BottomTab `t.name`
              navigation.navigate('Main', { screen: 'MainTabs', params: { screen: t.name } });
            }}
          >
            <TabIcon tab={t} color={color} />
            <Text style={[styles.label, { color }, isActive && styles.labelActive]}>{t.label}</Text>
          </TouchableOpacity>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingTop: 10,
    paddingHorizontal: 10,
    backgroundColor: 'rgba(255,255,255,0.96)',
    borderTopWidth: 1,
    borderTopColor: '#E5E7EB',
  },
  tab: { flex: 1, alignItems: 'center', paddingVertical: 6 },
  // Avoid relying on `gap` (not supported in all RN builds)
  label: { marginTop: 4, fontSize: 10, fontWeight: '700' },
  labelActive: { fontWeight: '900' },
});

