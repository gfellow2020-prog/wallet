import React, { useState } from 'react';
import { SafeAreaView, View, Text, TextInput, TouchableOpacity, StyleSheet, Image, Keyboard } from 'react-native';
import { Feather } from '@expo/vector-icons';
import { StatusBar } from 'expo-status-bar';
import * as ImagePicker from 'expo-image-picker';
import { useAuth } from '../context/AuthContext';
import { useDialog } from '../context/DialogContext';
import api from '../services/client';
import KeyboardFormScreen from '../components/KeyboardFormScreen';
import { compressImageAsset } from '../utils/imageCompression';

const APP_YEAR = new Date().getFullYear();

export default function Register({ navigation }) {
  const { register } = useAuth();
  const { alert } = useDialog();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phoneNumber, setPhoneNumber] = useState('');
  const [password, setPassword] = useState('');
  const [nrcNumber, setNrcNumber] = useState('');
  const [tpin, setTpin] = useState('');
  const [photo, setPhoto] = useState(null);
  const [agreed, setAgreed] = useState(false);
  const [loading, setLoading] = useState(false);
  const [verifying, setVerifying] = useState(false);
  const [otpId, setOtpId] = useState(null);
  const [otpCode, setOtpCode] = useState('');
  const [otpStep, setOtpStep] = useState(false);
  const { verifyRegisterOtp } = useAuth();

  const formatNrcInput = (text) => {
    const digits = (text || '').replace(/\D/g, '').slice(0, 9);
    if (digits.length <= 6) return digits;
    if (digits.length <= 8) return `${digits.slice(0, 6)}/${digits.slice(6)}`;
    return `${digits.slice(0, 6)}/${digits.slice(6, 8)}/${digits.slice(8)}`;
  };

  const pickTpinFromLookup = (d) => {
    if (d == null || typeof d !== 'object') return null;
    const direct =
      d.tpin
      ?? d.TPIN
      ?? d.Tpin
      ?? d.t_pin
      ?? d.tin
      ?? d.TIN
      ?? d.zra_tpin
      ?? d.ZRA_TPIN
      ?? d.taxpayer_number
      ?? d.tax_payer_number
      ?? d.taxpayer_no
      ?? d.taxpayerNo
      ?? d.TPINNo;
    if (direct != null && `${direct}`.trim() !== '') {
      return `${direct}`.trim();
    }
    const nestKeys = ['details', 'result', 'nrc', 'tax', 'person', 'customer', 'data'];
    for (const k of nestKeys) {
      const n = d[k];
      if (n && typeof n === 'object') {
        const t =
          n.tpin ?? n.TPIN ?? n.tin ?? n.TIN ?? n.taxpayer_number ?? n.zra_tpin;
        if (t != null && `${t}`.trim() !== '') {
          return `${t}`.trim();
        }
      }
    }
    return null;
  };

  const verifyNrc = async () => {
    if (!nrcNumber.trim()) return alert({ title: 'NRC required', message: 'Enter your NRC number first.', tone: 'warn' });
    setVerifying(true);
    try {
      const res = await api.post('/nrc/verify', { nrc_number: nrcNumber.trim() });
      if (!res?.data?.success) {
        return alert({
          title: 'Verification failed',
          message: res?.data?.message || 'Unable to verify NRC.',
          tone: 'danger',
        });
      }

      const lookupData = res?.data?.data || {};
      const fullName = lookupData?.full_name
        || [lookupData?.first_name, lookupData?.last_name].filter(Boolean).join(' ');
      const fetchedTpin = pickTpinFromLookup(lookupData) ?? res?.data?.tpin;

      if (fullName) setName(fullName);
      if (fetchedTpin !== undefined && fetchedTpin !== null && `${fetchedTpin}`.trim() !== '') {
        setTpin(`${fetchedTpin}`.trim());
      }

      await alert({
        title: 'NRC verified',
        message: 'Your full name and TPIN have been populated.',
        tone: 'success',
      });
    } catch (err) {
      const msg = err?.response?.data?.message || 'Unable to verify NRC right now.';
      await alert({ title: 'Verification error', message: msg, tone: 'danger' });
    } finally {
      setVerifying(false);
    }
  };

  const capturePhoto = async () => {
    const permission = await ImagePicker.requestCameraPermissionsAsync();
    if (!permission.granted) {
      return alert({
        title: 'Permission required',
        message: 'Camera permission is needed to capture your profile photo.',
        tone: 'warn',
      });
    }

    const result = await ImagePicker.launchCameraAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      allowsEditing: true,
      quality: 0.8,
    });

    if (result.canceled) return;
    const asset = result.assets?.[0];
    if (!asset?.uri) return;
    try {
      const compressed = await compressImageAsset(asset);
      setPhoto(compressed);
    } catch {
      setPhoto(asset);
    }
  };

  const submit = async () => {
    if (!nrcNumber || !name || !tpin || !email || !phoneNumber || !password || !photo?.uri) {
      return alert({
        title: 'Missing info',
        message: 'Please complete NRC verification, enter all fields (including mobile number), and capture your profile photo.',
        tone: 'warn',
      });
    }
    if (!agreed) {
      return alert({
        title: 'Accept Terms first',
        message: 'You need to agree to the Terms & Conditions to create an account.',
        tone: 'warn',
      });
    }
    setLoading(true);
    try {
      const form = new FormData();
      form.append('name', name.trim());
      form.append('email', email.trim().toLowerCase());
      form.append('phone_number', phoneNumber.trim());
      form.append('password', password);
      form.append('nrc_number', nrcNumber.trim());
      form.append('tpin', tpin.trim());
      form.append('profile_photo', {
        uri: photo.uri,
        name: `profile-${Date.now()}.jpg`,
        type: photo.mimeType || 'image/jpeg',
      });

      const res = await register(form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      if (res?.otp_required && res?.otp?.id) {
        await alert({ title: 'Verify your number', message: 'We sent an OTP to confirm your mobile number.', tone: 'success' });
        navigation.navigate('OtpVerify', {
          email: email.trim().toLowerCase(),
          otpId: Number(res.otp.id),
          purpose: 'phone_verify',
        });
        return;
      }
    } catch (err) {
      const errors = err?.response?.data?.errors;
      const msg = errors ? Object.values(errors).flat().join('\n') : (err?.response?.data?.message || 'Registration failed');
      await alert({ title: 'Registration failed', message: msg, tone: 'danger' });
    } finally {
      setLoading(false);
    }
  };

  const submitOtp = async () => {
    if (!otpId || !otpCode.trim()) {
      return alert({ title: 'OTP required', message: 'Enter the OTP sent to your phone/email.', tone: 'warn' });
    }
    setLoading(true);
    try {
      await verifyRegisterOtp({
        email: email.trim().toLowerCase(),
        otp_id: otpId,
        otp_code: otpCode.trim(),
      });
    } catch (err) {
      const msg = err?.response?.data?.message || 'OTP verification failed';
      await alert({ title: 'Verification failed', message: msg, tone: 'danger' });
    } finally {
      setLoading(false);
    }
  };

  const canSubmit = !loading && agreed;

  return (
    <SafeAreaView style={styles.container}>
      <StatusBar style="dark" />

      <KeyboardFormScreen contentContainerStyle={styles.scrollContent}>
        <View style={styles.hero}>
          <View style={styles.logoMark}>
            <Text style={styles.logoMarkText}>E</Text>
          </View>
          <Text style={styles.logoText}>ExtraCash</Text>
          <Text style={styles.subtitle}>Create your free account</Text>
        </View>

        <View style={styles.card}>
        <View style={styles.rowGap}>
          <TextInput
            placeholder="NRC number"
            placeholderTextColor="#9CA3AF"
            style={[styles.input, { marginBottom: 0, flex: 1 }]}
            value={nrcNumber}
            onChangeText={(value) => setNrcNumber(formatNrcInput(value))}
            keyboardType="number-pad"
            autoCapitalize="characters"
            autoCorrect={false}
            returnKeyType="next"
            blurOnSubmit={false}
          />
          <TouchableOpacity style={styles.verifyBtn} onPress={verifyNrc} disabled={verifying}>
            <Text style={styles.verifyBtnText}>{verifying ? 'Checking…' : 'Verify'}</Text>
          </TouchableOpacity>
        </View>
        <Text style={styles.fieldHint}>Enter NRC as 123456/78/9 then tap Verify.</Text>

        <TextInput
          placeholder="Full name"
          placeholderTextColor="#9CA3AF"
          style={styles.input}
          value={name}
          onChangeText={setName}
          autoCorrect={false}
          returnKeyType="next"
          blurOnSubmit={false}
        />
        <Text style={styles.fieldHint}>Auto-filled from NRC lookup (you can still edit).</Text>
        <TextInput
          placeholder="TPIN"
          placeholderTextColor="#9CA3AF"
          style={styles.input}
          value={tpin}
          onChangeText={setTpin}
          autoCorrect={false}
          returnKeyType="next"
          blurOnSubmit={false}
        />
        <Text style={styles.fieldHint}>TPIN is fetched from NRC lookup when available.</Text>
        <TextInput
          placeholder="Email address"
          placeholderTextColor="#9CA3AF"
          style={styles.input}
          value={email}
          onChangeText={setEmail}
          keyboardType="email-address"
          autoCapitalize="none"
          autoCorrect={false}
          returnKeyType="next"
          blurOnSubmit={false}
        />
        <Text style={styles.fieldHint}>Use an active email for account access.</Text>
        <TextInput
          placeholder="Mobile number (e.g. 0977…)"
          placeholderTextColor="#9CA3AF"
          style={styles.input}
          value={phoneNumber}
          onChangeText={setPhoneNumber}
          keyboardType="phone-pad"
          autoCapitalize="none"
          autoCorrect={false}
          returnKeyType="next"
          blurOnSubmit={false}
        />
        <Text style={styles.fieldHint}>We’ll send an OTP to confirm you own this number.</Text>
        <TextInput
          placeholder="Password"
          placeholderTextColor="#9CA3AF"
          style={styles.input}
          secureTextEntry
          value={password}
          onChangeText={setPassword}
          returnKeyType="done"
          onSubmitEditing={Keyboard.dismiss}
        />
        <Text style={styles.fieldHint}>Use at least 8 characters for better security.</Text>

        <TouchableOpacity style={styles.photoCard} onPress={capturePhoto} activeOpacity={0.85}>
          <View style={styles.photoThumb}>
            {photo?.uri ? (
              <Image source={{ uri: photo.uri }} style={styles.photoThumbImg} />
            ) : (
              <Feather name="user" size={18} color="#9CA3AF" />
            )}
            <View style={styles.photoBadge}>
              <Feather name="camera" size={12} color="#111827" />
            </View>
          </View>
          <View style={{ flex: 1, minWidth: 0 }}>
            <Text style={styles.photoLabel}>Profile photo</Text>
            <Text style={styles.photoHelp} numberOfLines={1}>
              {photo?.uri ? 'Tap to retake (camera)' : 'Required • tap to take (camera)'}
            </Text>
          </View>
          <Feather name="chevron-right" size={18} color="#9CA3AF" />
        </TouchableOpacity>

        {otpStep ? (
          <>
            <TextInput
              placeholder="Enter OTP"
              placeholderTextColor="#9CA3AF"
              style={styles.input}
              value={otpCode}
              onChangeText={setOtpCode}
              keyboardType="number-pad"
              returnKeyType="done"
              onSubmitEditing={Keyboard.dismiss}
            />
            <TouchableOpacity
              style={[styles.button, loading && styles.buttonDisabled]}
              onPress={submitOtp}
              disabled={loading}
            >
              <Text style={styles.buttonText}>{loading ? 'Verifying…' : 'Verify OTP & Continue'}</Text>
            </TouchableOpacity>
          </>
        ) : null}

        <TouchableOpacity
          style={styles.consentRow}
          onPress={() => setAgreed((v) => !v)}
          activeOpacity={0.7}
          accessibilityRole="checkbox"
          accessibilityState={{ checked: agreed }}
        >
          <View style={[styles.checkbox, agreed && styles.checkboxChecked]}>
            {agreed ? <Feather name="check" size={14} color="#fff" /> : null}
          </View>
          <Text style={styles.consentText}>
            I agree to the{' '}
            <Text style={styles.consentLink}>Terms & Conditions</Text> and{' '}
            <Text style={styles.consentLink}>Privacy Policy</Text>.
          </Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.button, !canSubmit && styles.buttonDisabled]}
          onPress={submit}
          disabled={!canSubmit}
        >
          <Text style={styles.buttonText}>{loading ? 'Creating…' : 'Create account'}</Text>
        </TouchableOpacity>

        <TouchableOpacity style={styles.switchRow} onPress={() => navigation.navigate('Login')}>
          <Text style={styles.switchText}>Already have an account? </Text>
          <Text style={styles.link}>Sign in</Text>
        </TouchableOpacity>
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
    paddingHorizontal: 24,
    paddingTop: 24,
    paddingBottom: 24,
  },
  hero: { alignItems: 'center', marginBottom: 24 },
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
  subtitle: { fontSize: 14, color: '#6B7280', marginTop: 6 },
  card: {},
  rowGap: { flexDirection: 'row', gap: 8, marginBottom: 12 },
  input: { borderRadius: 10, borderWidth: 1, borderColor: '#E5E7EB', paddingHorizontal: 14, paddingVertical: 14, marginBottom: 12, color: '#000', backgroundColor: '#F9FAFB', fontSize: 15 },
  fieldHint: { fontSize: 11, color: '#9CA3AF', marginTop: -4, marginBottom: 12, paddingHorizontal: 2 },
  verifyBtn: { backgroundColor: '#111827', borderRadius: 10, paddingHorizontal: 14, justifyContent: 'center' },
  verifyBtnText: { color: '#fff', fontWeight: '700', fontSize: 13 },
  photoCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    backgroundColor: '#F9FAFB',
    paddingHorizontal: 12,
    paddingVertical: 12,
    marginBottom: 12,
  },
  photoThumb: {
    width: 44,
    height: 44,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    backgroundColor: '#fff',
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
  },
  photoThumbImg: { width: 44, height: 44 },
  photoBadge: {
    position: 'absolute',
    right: -2,
    bottom: -2,
    width: 18,
    height: 18,
    borderRadius: 6,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    backgroundColor: '#fff',
    alignItems: 'center',
    justifyContent: 'center',
  },
  photoLabel: { fontSize: 13, fontWeight: '800', color: '#111827' },
  photoHelp: { marginTop: 2, fontSize: 11, color: '#9CA3AF' },
  consentRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
    paddingVertical: 6,
    marginTop: 4,
    marginBottom: 14,
  },
  checkbox: {
    width: 20,
    height: 20,
    borderRadius: 5,
    borderWidth: 1.5,
    borderColor: '#9CA3AF',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 1,
    backgroundColor: '#fff',
  },
  checkboxChecked: { backgroundColor: '#111827', borderColor: '#111827' },
  consentText: { flex: 1, fontSize: 12, color: '#4B5563', lineHeight: 18 },
  consentLink: { color: '#111827', fontWeight: '700' },
  button: { backgroundColor: '#000', paddingVertical: 14, borderRadius: 10, alignItems: 'center', marginTop: 4 },
  buttonDisabled: { backgroundColor: '#9CA3AF' },
  buttonText: { color: '#fff', fontWeight: '700', fontSize: 15 },
  switchRow: { flexDirection: 'row', justifyContent: 'center', marginTop: 20 },
  switchText: { color: '#6B7280', fontSize: 14 },
  link: { color: '#111827', fontWeight: '700', fontSize: 14 },
  copyright: {
    fontSize: 11,
    color: '#9CA3AF',
    textAlign: 'center',
    marginTop: 24,
  },
});
