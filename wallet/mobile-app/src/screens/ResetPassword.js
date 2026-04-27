import React, { useMemo, useState } from 'react';
import { SafeAreaView, View, Text, TextInput, TouchableOpacity, StyleSheet, Keyboard } from 'react-native';
import { StatusBar } from 'expo-status-bar';
import { useAuth } from '../context/AuthContext';
import { useDialog } from '../context/DialogContext';
import KeyboardFormScreen from '../components/KeyboardFormScreen';
import { UI } from '../components/ui';

const APP_YEAR = new Date().getFullYear();

export default function ResetPassword({ route }) {
  const { resetPassword } = useAuth();
  const { alert } = useDialog();

  const resetSession = `${route?.params?.resetSession || ''}`.trim();
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [loading, setLoading] = useState(false);

  const canSubmit = useMemo(() => {
    return !loading && password.length >= 8 && password === confirm && resetSession !== '';
  }, [loading, password, confirm, resetSession]);

  const submit = async () => {
    if (resetSession === '') {
      return alert({ title: 'Session expired', message: 'Please request a new OTP.', tone: 'warn' });
    }
    if (password.length < 8) {
      return alert({ title: 'Password too short', message: 'Use at least 8 characters.', tone: 'warn' });
    }
    if (password !== confirm) {
      return alert({ title: 'Passwords do not match', message: 'Confirm your new password.', tone: 'warn' });
    }

    setLoading(true);
    try {
      await resetPassword({ reset_session: resetSession, password });
      // On success AuthContext sets the token+user, RootNavigator will switch to authed area.
    } catch (err) {
      const msg = err?.response?.data?.message || 'Could not reset password.';
      await alert({ title: 'Reset failed', message: msg, tone: 'danger' });
    } finally {
      setLoading(false);
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <StatusBar style="dark" />
      <KeyboardFormScreen contentContainerStyle={styles.scrollContent}>
        <View style={styles.body}>
          <View style={styles.hero}>
            <View style={styles.logoMark}>
              <Text style={styles.logoMarkText}>E</Text>
            </View>
            <Text style={styles.logoText}>ExtraCash</Text>
            <Text style={styles.subtitle}>Create a new password</Text>
          </View>

          <View style={styles.card}>
            <TextInput
              placeholder="New password"
              placeholderTextColor="#9CA3AF"
              style={styles.input}
              value={password}
              onChangeText={setPassword}
              secureTextEntry
              autoCapitalize="none"
              autoCorrect={false}
              returnKeyType="next"
              blurOnSubmit={false}
            />
            <Text style={styles.fieldHint}>Use at least 8 characters.</Text>

            <TextInput
              placeholder="Confirm new password"
              placeholderTextColor="#9CA3AF"
              style={styles.input}
              value={confirm}
              onChangeText={setConfirm}
              secureTextEntry
              autoCapitalize="none"
              autoCorrect={false}
              returnKeyType="done"
              onSubmitEditing={Keyboard.dismiss}
            />

            <TouchableOpacity
              style={[styles.button, !canSubmit && styles.buttonDisabled]}
              onPress={submit}
              disabled={!canSubmit}
            >
              <Text style={styles.buttonText}>{loading ? 'Updating…' : 'Set password & Sign in'}</Text>
            </TouchableOpacity>
          </View>
        </View>

        <Text style={styles.copyright}>© {APP_YEAR} ExtraCash. All rights reserved.</Text>
      </KeyboardFormScreen>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#fff' },
  scrollContent: {
    flexGrow: 1,
    justifyContent: 'space-between',
    paddingHorizontal: 24,
    paddingTop: 24,
    paddingBottom: 24,
  },
  body: { flex: 1, justifyContent: 'center', paddingVertical: 24 },
  hero: { alignItems: 'center', marginBottom: 28 },
  logoMark: {
    width: 56,
    height: 56,
    borderRadius: 16,
    backgroundColor: '#000',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 14,
  },
  logoMarkText: { color: '#fff', fontWeight: '800', fontSize: 26, letterSpacing: -0.5 },
  logoText: { fontSize: 26, fontWeight: '800', color: '#111827', letterSpacing: -0.5 },
  subtitle: { fontSize: 14, color: '#6B7280', marginTop: 6, textAlign: 'center' },
  card: {},
  input: {
    borderRadius: UI.radius.input,
    borderWidth: 1,
    borderColor: UI.colors.border,
    paddingHorizontal: 14,
    paddingVertical: 14,
    marginBottom: 12,
    color: UI.colors.primary,
    backgroundColor: UI.colors.bg,
    fontSize: 15,
  },
  fieldHint: { fontSize: 11, color: UI.colors.disabled, marginTop: -4, marginBottom: 12, paddingHorizontal: 2 },
  button: { backgroundColor: UI.colors.primary, paddingVertical: 14, borderRadius: UI.radius.input, alignItems: 'center', marginTop: 4 },
  buttonDisabled: { backgroundColor: UI.colors.disabled },
  buttonText: { color: '#fff', fontWeight: '700', fontSize: 15 },
  copyright: { fontSize: 11, color: UI.colors.disabled, textAlign: 'center', marginTop: 16 },
});

