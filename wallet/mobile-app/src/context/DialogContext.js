import React, { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import {
  Modal,
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  Pressable,
  ActivityIndicator,
} from 'react-native';
import { Feather } from '@expo/vector-icons';

const DialogContext = createContext(null);

/**
 * A single app-wide modal that replaces React Native's native Alert.
 *
 * Imperative API:
 *   const { confirm, alert, show } = useDialog();
 *
 *   const ok = await confirm({ title, message, confirmLabel, cancelLabel, tone, icon, details });
 *   await alert({ title, message, tone, icon, confirmLabel });
 *   const key = await show({ title, message, actions: [{ key, label, style }] });
 */

const TONE_STYLES = {
  default: { iconBg: '#111827', iconFg: '#fff',     primary: '#111827', icon: 'info' },
  success: { iconBg: '#10B981', iconFg: '#fff',     primary: '#111827', icon: 'check' },
  danger:  { iconBg: '#FEE2E2', iconFg: '#DC2626',  primary: '#DC2626', icon: 'alert-triangle' },
  warn:    { iconBg: '#FEF3C7', iconFg: '#D97706',  primary: '#111827', icon: 'alert-circle' },
  info:    { iconBg: '#DBEAFE', iconFg: '#2563EB',  primary: '#111827', icon: 'info' },
};

function resolveTone(tone) {
  return TONE_STYLES[tone] || TONE_STYLES.default;
}

const noop = () => {};

export function DialogProvider({ children }) {
  const [current, setCurrent] = useState(null);
  const [busyKey, setBusyKey] = useState(null);
  const queueRef = useRef([]);

  /**
   * Show a dialog. Returns a Promise that resolves to the key of the pressed
   * action (or null if dismissed via backdrop).
   *
   * Uses the functional form of setCurrent so we never rely on a closed-over
   * `current` — this keeps `show` (and therefore `confirm`/`alert`) stable
   * and immune to stale-closure bugs when one dialog chains into another.
   */
  const show = useCallback((opts = {}) => {
    return new Promise((resolve) => {
      const payload = {
        title:       opts.title,
        message:     opts.message,
        details:     Array.isArray(opts.details) ? opts.details : null,
        tone:        opts.tone || 'default',
        icon:        opts.icon,
        dismissible: opts.dismissible !== false,
        actions: (opts.actions || []).map((a, i) => ({
          key:     a.key || `action-${i}`,
          label:   a.label,
          style:   a.style || (i === 0 ? 'primary' : 'cancel'),
          onPress: a.onPress,
        })),
        resolve,
      };

      setCurrent((prev) => {
        if (prev) {
          // Another dialog is currently visible — queue this one.
          queueRef.current.push(payload);
          return prev;
        }
        return payload;
      });
    });
  }, []);

  // Drain the queue whenever the visible dialog closes.
  useEffect(() => {
    if (!current && queueRef.current.length > 0) {
      const next = queueRef.current.shift();
      setCurrent(next);
    }
  }, [current]);

  const confirm = useCallback(async ({
    confirmLabel = 'Confirm',
    cancelLabel  = 'Cancel',
    confirmTone,
    ...rest
  } = {}) => {
    const key = await show({
      ...rest,
      actions: [
        { key: 'confirm', label: confirmLabel, style: confirmTone === 'danger' ? 'danger' : 'primary' },
        { key: 'cancel',  label: cancelLabel,  style: 'cancel' },
      ],
    });
    return key === 'confirm';
  }, [show]);

  const alert = useCallback(async ({
    confirmLabel = 'OK',
    ...rest
  } = {}) => {
    await show({
      ...rest,
      actions: [{ key: 'ok', label: confirmLabel, style: 'primary' }],
    });
  }, [show]);

  const handlePress = useCallback(async (action) => {
    if (action.onPress) {
      setBusyKey(action.key);
      try {
        const result = action.onPress();
        if (result && typeof result.then === 'function') await result;
      } finally {
        setBusyKey(null);
      }
    }
    setCurrent((prev) => {
      if (prev) prev.resolve?.(action.key);
      return null;
    });
  }, []);

  const handleBackdrop = useCallback(() => {
    setCurrent((prev) => {
      if (!prev || !prev.dismissible) return prev;
      prev.resolve?.(null);
      return null;
    });
  }, []);

  const value = useMemo(() => ({ show, confirm, alert }), [show, confirm, alert]);

  const toneStyle = resolveTone(current?.tone);
  const iconName  = current?.icon || toneStyle.icon;

  return (
    <DialogContext.Provider value={value}>
      {children}
      <Modal
        visible={!!current}
        transparent
        animationType="fade"
        onRequestClose={handleBackdrop}
      >
        <Pressable style={styles.backdrop} onPress={handleBackdrop}>
          {/* The card claims its own touches so tapping inside doesn't close. */}
          <Pressable style={styles.card} onPress={noop}>
            {iconName ? (
              <View style={[styles.iconCircle, { backgroundColor: toneStyle.iconBg }]}>
                <Feather name={iconName} size={26} color={toneStyle.iconFg} />
              </View>
            ) : null}

            {current?.title   ? <Text style={styles.title}>{current.title}</Text>     : null}
            {current?.message ? <Text style={styles.message}>{current.message}</Text> : null}

            {current?.details?.length ? (
              <View style={styles.details}>
                {current.details.map((row, idx) => (
                  <View
                    key={`row-${idx}`}
                    style={[
                      styles.detailRow,
                      idx === current.details.length - 1 && styles.detailRowLast,
                    ]}
                  >
                    <Text style={styles.detailLabel}>{row.label}</Text>
                    <Text
                      style={[
                        styles.detailValue,
                        row.tone === 'success' && { color: '#047857' },
                        row.tone === 'danger'  && { color: '#B91C1C' },
                        row.tone === 'muted'   && { color: '#6B7280' },
                      ]}
                      numberOfLines={2}
                    >
                      {row.value}
                    </Text>
                  </View>
                ))}
              </View>
            ) : null}

            {current?.actions?.length ? (
              <View style={styles.actions}>
                {current.actions.map((action) => {
                  const isBusy = busyKey === action.key;
                  const { containerStyle, textStyle } = getActionStyles(action.style, toneStyle);
                  return (
                    <TouchableOpacity
                      key={action.key}
                      style={[styles.actionBtn, containerStyle]}
                      onPress={() => handlePress(action)}
                      disabled={busyKey !== null}
                      activeOpacity={0.85}
                    >
                      {isBusy ? (
                        <ActivityIndicator color={textStyle.color || '#fff'} />
                      ) : (
                        <Text style={[styles.actionText, textStyle]}>{action.label}</Text>
                      )}
                    </TouchableOpacity>
                  );
                })}
              </View>
            ) : null}
          </Pressable>
        </Pressable>
      </Modal>
    </DialogContext.Provider>
  );
}

function getActionStyles(style, toneStyle) {
  switch (style) {
    case 'danger':
      return { containerStyle: { backgroundColor: '#DC2626' }, textStyle: { color: '#fff', fontWeight: '800' } };
    case 'success':
      return { containerStyle: { backgroundColor: '#10B981' }, textStyle: { color: '#fff', fontWeight: '800' } };
    case 'secondary':
      return {
        containerStyle: { backgroundColor: '#F3F4F6', borderWidth: 1, borderColor: '#E5E7EB' },
        textStyle:      { color: '#111827', fontWeight: '800' },
      };
    case 'cancel':
      return {
        containerStyle: { backgroundColor: 'transparent', paddingVertical: 10 },
        textStyle:      { color: '#6B7280', fontWeight: '700' },
      };
    case 'primary':
    default:
      return { containerStyle: { backgroundColor: toneStyle.primary }, textStyle: { color: '#fff', fontWeight: '800' } };
  }
}

const NOOP_ASYNC = async () => {};

/**
 * Hook: useDialog() — returns { confirm, alert, show }.
 * Returns no-op async functions if called outside the provider so it's
 * still safe to call (won't crash), though nothing will render.
 */
export function useDialog() {
  const ctx = useContext(DialogContext);
  if (!ctx) {
    return {
      confirm: async () => false,
      alert:   NOOP_ASYNC,
      show:    async () => null,
    };
  }
  return ctx;
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(17, 24, 39, 0.55)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  card: {
    width: '100%',
    maxWidth: 400,
    backgroundColor: '#fff',
    borderRadius: 20,
    padding: 22,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOpacity: 0.12,
    shadowRadius: 18,
    shadowOffset: { width: 0, height: 6 },
    elevation: 8,
  },
  iconCircle: {
    width: 56,
    height: 56,
    borderRadius: 28,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
  },
  title: {
    fontSize: 17,
    fontWeight: '900',
    color: '#111827',
    textAlign: 'center',
    marginBottom: 6,
  },
  message: {
    fontSize: 13,
    color: '#4B5563',
    textAlign: 'center',
    lineHeight: 19,
  },

  details: {
    alignSelf: 'stretch',
    marginTop: 14,
    borderWidth: 1,
    borderColor: '#F3F4F6',
    borderRadius: 12,
    overflow: 'hidden',
  },
  detailRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
    gap: 10,
  },
  detailRowLast: { borderBottomWidth: 0 },
  detailLabel: { fontSize: 12, color: '#6B7280', fontWeight: '700' },
  detailValue: {
    flexShrink: 1,
    textAlign: 'right',
    fontSize: 13,
    color: '#111827',
    fontWeight: '800',
  },

  actions: { alignSelf: 'stretch', marginTop: 16, gap: 8 },
  actionBtn: {
    alignSelf: 'stretch',
    borderRadius: 12,
    paddingVertical: 13,
    alignItems: 'center',
    justifyContent: 'center',
  },
  actionText: { fontSize: 14 },
});
