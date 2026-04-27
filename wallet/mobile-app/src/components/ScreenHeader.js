import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { Feather } from '@expo/vector-icons';

export default function ScreenHeader({
  title,
  subtitle,
  leftIcon = 'arrow-left',
  onLeftPress,
  right,
}) {
  return (
    <View style={styles.wrap}>
      <TouchableOpacity
        onPress={onLeftPress}
        style={styles.leftBtn}
        activeOpacity={0.75}
        hitSlop={{ top: 12, bottom: 12, left: 12, right: 12 }}
        accessibilityRole="button"
      >
        <Feather name={leftIcon} size={20} color="#111827" />
      </TouchableOpacity>

      <View style={styles.center}>
        <Text style={styles.title} numberOfLines={1}>{title}</Text>
        {subtitle ? <Text style={styles.subtitle} numberOfLines={1}>{subtitle}</Text> : null}
      </View>

      <View style={styles.right}>
        {right ?? <View style={{ width: 36 }} />}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: '#fff',
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#E5E7EB',
  },
  leftBtn: {
    width: 36,
    height: 36,
    borderRadius: 10,
    backgroundColor: '#F3F4F6',
    alignItems: 'center',
    justifyContent: 'center',
  },
  center: { flex: 1, alignItems: 'center', paddingHorizontal: 10 },
  title: { fontSize: 16, fontWeight: '900', color: '#111827' },
  subtitle: { marginTop: 2, fontSize: 11, fontWeight: '700', color: '#6B7280' },
  right: { width: 36, alignItems: 'flex-end' },
});

