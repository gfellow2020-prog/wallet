import React from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet } from 'react-native';

/**
 * Scrollable form area that moves above the software keyboard and lets users
 * scroll while focused. Use inside SafeAreaView with flex:1.
 *
 * - iOS: padding shift + dismiss keyboard by dragging the scroll
 * - Android: relies on app.json softwareKeyboardLayoutMode: resize; scroll stays usable
 */
export default function KeyboardFormScreen({
  children,
  contentContainerStyle,
  scrollStyle,
  /** Extra offset when a stack header or custom bar sits above the form (iOS). */
  keyboardVerticalOffset = 0,
  ...scrollProps
}) {
  return (
    <KeyboardAvoidingView
      style={styles.flex}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      keyboardVerticalOffset={keyboardVerticalOffset}
    >
      <ScrollView
        style={[styles.scroll, scrollStyle]}
        contentContainerStyle={[styles.content, contentContainerStyle]}
        keyboardShouldPersistTaps="handled"
        keyboardDismissMode="on-drag"
        showsVerticalScrollIndicator
        nestedScrollEnabled
        {...scrollProps}
      >
        {children}
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
  scroll: { flex: 1 },
  content: { flexGrow: 1, paddingBottom: 40 },
});
