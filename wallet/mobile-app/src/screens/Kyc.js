import React, { useState, useEffect, useCallback } from 'react';
import {
  View, Text, StyleSheet, TouchableOpacity,
  ActivityIndicator, TextInput, Keyboard,
} from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import * as DocumentPicker from 'expo-document-picker';
import { Feather } from '@expo/vector-icons';
import client from '../services/client';
import { useDialog } from '../context/DialogContext';
import KeyboardFormScreen from '../components/KeyboardFormScreen';
import { compressImageAsset } from '../utils/imageCompression';

const ID_TYPES = [
  { label: 'National ID',        value: 'national_id' },
  { label: 'Passport',           value: 'passport' },
  { label: "Driver's License",   value: 'drivers_license' },
];

const STATUS_CONFIG = {
  not_submitted: { color: '#6B7280', icon: 'alert-circle',   label: 'Not Submitted' },
  pending:       { color: '#F59E0B', icon: 'clock',          label: 'Under Review'  },
  verified:      { color: '#10B981', icon: 'check-circle',   label: 'Verified'      },
  rejected:      { color: '#EF4444', icon: 'x-circle',       label: 'Rejected'      },
  expired:       { color: '#6B7280', icon: 'alert-triangle', label: 'Expired'       },
};

export default function KycScreen({ navigation }) {
  const { alert } = useDialog();
  const [kycStatus, setKycStatus]   = useState(null);
  const [kycRecord, setKycRecord]   = useState(null);
  const [loading, setLoading]       = useState(true);
  const [submitting, setSubmitting] = useState(false);

  // Form state
  const [idType, setIdType]         = useState('national_id');
  const [idNumber, setIdNumber]     = useState('');
  const [idDoc, setIdDoc]           = useState(null);   // { uri, name, type }
  const [selfie, setSelfie]         = useState(null);   // { uri, name, type }

  const fetchStatus = useCallback(async () => {
    try {
      const { data } = await client.get('/kyc/status');
      setKycStatus(data.status);
      setKycRecord(data.kyc);
    } catch {
      setKycStatus('not_submitted');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchStatus(); }, [fetchStatus]);

  const pickDocument = async () => {
    const result = await DocumentPicker.getDocumentAsync({
      type: ['image/*', 'application/pdf'],
      copyToCacheDirectory: true,
    });
    if (!result.canceled && result.assets?.length) {
      const asset = result.assets[0];
      if ((asset.mimeType || '').startsWith('image/')) {
        try {
          const compressed = await compressImageAsset({ uri: asset.uri });
          setIdDoc({ uri: compressed.uri, name: asset.name || compressed.fileName || 'id.jpg', type: 'image/jpeg' });
          return;
        } catch {
          // fall back
        }
      }
      setIdDoc({ uri: asset.uri, name: asset.name, type: asset.mimeType });
    }
  };

  const pickSelfie = async () => {
    const { status } = await ImagePicker.requestCameraPermissionsAsync();
    if (status !== 'granted') {
      await alert({
        title: 'Permission needed',
        message: 'Camera access is required for your selfie.',
        tone: 'warn',
      });
      return;
    }
    const result = await ImagePicker.launchCameraAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      quality: 0.7,
      allowsEditing: true,
      aspect: [1, 1],
    });
    if (!result.canceled && result.assets?.length) {
      const asset = result.assets[0];
      try {
        const compressed = await compressImageAsset(asset);
        setSelfie({ uri: compressed.uri, name: 'selfie.jpg', type: 'image/jpeg' });
      } catch {
        setSelfie({ uri: asset.uri, name: 'selfie.jpg', type: asset.mimeType || 'image/jpeg' });
      }
    }
  };

  const handleSubmit = async () => {
    if (!idNumber.trim()) { await alert({ title: 'Missing field',  message: 'Please enter your ID number.',      tone: 'warn' }); return; }
    if (!idDoc)           { await alert({ title: 'Missing file',   message: 'Please upload your ID document.',   tone: 'warn' }); return; }
    if (!selfie)          { await alert({ title: 'Missing selfie', message: 'Please take a selfie photo.',       tone: 'warn' }); return; }

    const form = new FormData();
    form.append('id_type',   idType);
    form.append('id_number', idNumber.trim());
    form.append('id_document', { uri: idDoc.uri,   name: idDoc.name,   type: idDoc.type });
    form.append('selfie',      { uri: selfie.uri,  name: selfie.name,  type: selfie.type });

    try {
      setSubmitting(true);
      await client.post('/kyc', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      await alert({
        title: 'Submitted!',
        message: 'Your KYC documents have been submitted. We\'ll review within 24 hours.',
        tone: 'success',
      });
      fetchStatus();
    } catch (err) {
      await alert({
        title: 'Submission failed',
        message: err.response?.data?.message ?? 'Submission failed. Please try again.',
        tone: 'danger',
      });
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#000" />
      </View>
    );
  }

  const cfg = STATUS_CONFIG[kycStatus] ?? STATUS_CONFIG.not_submitted;
  const canSubmit = ['not_submitted', 'rejected'].includes(kycStatus);

  return (
    <KeyboardFormScreen scrollStyle={styles.container} contentContainerStyle={styles.content}>
      {/* Header */}
      <View style={styles.headerRow}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Feather name="arrow-left" size={22} color="#000" />
        </TouchableOpacity>
        <Text style={styles.title}>Identity Verification</Text>
      </View>

      {/* Status Badge */}
      <View style={[styles.statusCard, { borderColor: cfg.color }]}>
        <Feather name={cfg.icon} size={32} color={cfg.color} />
        <View style={{ marginLeft: 14 }}>
          <Text style={styles.statusLabel}>KYC Status</Text>
          <Text style={[styles.statusValue, { color: cfg.color }]}>{cfg.label}</Text>
        </View>
      </View>

      {kycRecord?.review_notes && (
        <View style={styles.notesCard}>
          <Feather name="info" size={16} color="#EF4444" style={{ marginRight: 8 }} />
          <Text style={styles.notesText}>{kycRecord.review_notes}</Text>
        </View>
      )}

      {/* Verified state */}
      {kycStatus === 'verified' && (
        <View style={styles.verifiedCard}>
          <Feather name="shield" size={40} color="#10B981" />
          <Text style={styles.verifiedTitle}>You're verified!</Text>
          <Text style={styles.verifiedSub}>
            Your identity has been confirmed. You can now make withdrawals.
          </Text>
        </View>
      )}

      {/* Pending state */}
      {kycStatus === 'pending' && (
        <View style={styles.infoCard}>
          <Text style={styles.infoText}>
            Your documents are being reviewed. This usually takes less than 24 hours.
            You'll be notified once the review is complete.
          </Text>
        </View>
      )}

      {/* Submission form */}
      {canSubmit && (
        <>
          <Text style={styles.sectionTitle}>Submit Your Documents</Text>
          <Text style={styles.sectionSub}>
            We need to verify your identity before enabling withdrawals.
          </Text>

          {/* ID Type selector */}
          <Text style={styles.fieldLabel}>ID Type</Text>
          <View style={styles.typeRow}>
            {ID_TYPES.map(t => (
              <TouchableOpacity
                key={t.value}
                style={[styles.typeBtn, idType === t.value && styles.typeBtnActive]}
                onPress={() => setIdType(t.value)}
              >
                <Text style={[styles.typeBtnText, idType === t.value && styles.typeBtnTextActive]}>
                  {t.label}
                </Text>
              </TouchableOpacity>
            ))}
          </View>

          {/* ID Number */}
          <Text style={styles.fieldLabel}>ID Number</Text>
          <View style={styles.inputBox}>
            <Feather name="credit-card" size={18} color="#6B7280" style={{ marginRight: 10 }} />
            <TextInput
              style={styles.inputText}
              value={idNumber}
              onChangeText={setIdNumber}
              placeholder="Enter ID number"
              placeholderTextColor="#9CA3AF"
              autoCapitalize="characters"
              autoCorrect={false}
              returnKeyType="done"
              onSubmitEditing={Keyboard.dismiss}
            />
          </View>

          {/* ID Document */}
          <Text style={styles.fieldLabel}>ID Document (JPG, PNG, or PDF)</Text>
          <TouchableOpacity style={styles.uploadBtn} onPress={pickDocument}>
            <Feather name={idDoc ? 'check-circle' : 'upload'} size={20} color={idDoc ? '#10B981' : '#000'} />
            <Text style={[styles.uploadText, idDoc && { color: '#10B981' }]}>
              {idDoc ? idDoc.name : 'Choose file…'}
            </Text>
          </TouchableOpacity>

          {/* Selfie */}
          <Text style={styles.fieldLabel}>Selfie Photo</Text>
          <TouchableOpacity style={styles.uploadBtn} onPress={pickSelfie}>
            <Feather name={selfie ? 'check-circle' : 'camera'} size={20} color={selfie ? '#10B981' : '#000'} />
            <Text style={[styles.uploadText, selfie && { color: '#10B981' }]}>
              {selfie ? 'Selfie captured ✓' : 'Take selfie with camera'}
            </Text>
          </TouchableOpacity>

          {/* Submit */}
          <TouchableOpacity
            style={[styles.submitBtn, submitting && { opacity: 0.6 }]}
            onPress={handleSubmit}
            disabled={submitting}
          >
            {submitting
              ? <ActivityIndicator color="#fff" />
              : <Text style={styles.submitText}>Submit for Verification</Text>
            }
          </TouchableOpacity>
        </>
      )}
    </KeyboardFormScreen>
  );
}

const styles = StyleSheet.create({
  container:     { flex: 1, backgroundColor: '#fff' },
  content:       { padding: 24, paddingBottom: 48 },
  center:        { flex: 1, justifyContent: 'center', alignItems: 'center' },
  headerRow:     { flexDirection: 'row', alignItems: 'center', marginBottom: 28 },
  backBtn:       { marginRight: 14, padding: 4 },
  title:         { fontSize: 22, fontWeight: '700', color: '#111' },
  statusCard:    {
    flexDirection: 'row', alignItems: 'center',
    borderWidth: 1.5, borderRadius: 12, padding: 18, marginBottom: 16,
  },
  statusLabel:   { fontSize: 12, color: '#6B7280', marginBottom: 2 },
  statusValue:   { fontSize: 18, fontWeight: '700' },
  notesCard:     {
    flexDirection: 'row', alignItems: 'center',
    backgroundColor: '#FEF2F2', borderRadius: 10, padding: 14, marginBottom: 20,
  },
  notesText:     { flex: 1, color: '#B91C1C', fontSize: 13 },
  verifiedCard:  { alignItems: 'center', paddingVertical: 32 },
  verifiedTitle: { fontSize: 22, fontWeight: '700', color: '#10B981', marginTop: 12 },
  verifiedSub:   { textAlign: 'center', color: '#6B7280', marginTop: 8, lineHeight: 20 },
  infoCard:      {
    backgroundColor: '#FFFBEB', borderRadius: 10, padding: 16, marginBottom: 20,
  },
  infoText:      { color: '#92400E', lineHeight: 20 },
  sectionTitle:  { fontSize: 18, fontWeight: '700', color: '#111', marginTop: 8 },
  sectionSub:    { color: '#6B7280', marginTop: 4, marginBottom: 24, lineHeight: 20 },
  fieldLabel:    { fontSize: 13, fontWeight: '600', color: '#374151', marginBottom: 8 },
  typeRow:       { flexDirection: 'row', gap: 8, marginBottom: 20 },
  typeBtn:       {
    flex: 1, borderWidth: 1.5, borderColor: '#D1D5DB',
    borderRadius: 8, paddingVertical: 10, alignItems: 'center',
  },
  typeBtnActive: { borderColor: '#000', backgroundColor: '#000' },
  typeBtnText:   { fontSize: 12, color: '#374151', fontWeight: '500' },
  typeBtnTextActive: { color: '#fff' },
  inputBox:      {
    flexDirection: 'row', alignItems: 'center',
    borderWidth: 1.5, borderColor: '#D1D5DB', borderRadius: 10,
    paddingHorizontal: 14, paddingVertical: 14, marginBottom: 20,
  },
  inputText:     { fontSize: 15, color: '#111', flex: 1 },
  uploadBtn:     {
    flexDirection: 'row', alignItems: 'center', gap: 12,
    borderWidth: 1.5, borderColor: '#D1D5DB', borderStyle: 'dashed',
    borderRadius: 10, padding: 16, marginBottom: 20,
  },
  uploadText:    { fontSize: 14, color: '#374151' },
  submitBtn:     {
    backgroundColor: '#000', borderRadius: 12,
    paddingVertical: 16, alignItems: 'center', marginTop: 8,
  },
  submitText:    { color: '#fff', fontSize: 16, fontWeight: '700' },
});
