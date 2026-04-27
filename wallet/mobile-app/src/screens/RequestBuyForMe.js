import React, { useCallback, useEffect, useState } from 'react';
import {
  View,
  Text,
  Image,
  ScrollView,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  Share,
  Clipboard,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { Feather } from '@expo/vector-icons';
import QRCode from 'react-native-qrcode-svg';
import api from '../services/client';
import { useDialog } from '../context/DialogContext';
import { ensureDirectConversation } from '../services/messaging';
import ScreenHeader from '../components/ScreenHeader';

const CURRENCY = 'ZMW';
const fmt = (n) => `${CURRENCY} ${Number(n || 0).toFixed(2)}`;

/**
 * Requester-side "Buy for Me" page.
 *
 * The user lands here from the product detail "Get it → Buy for Me" flow.
 * Purpose: mint a BuyRequest on the backend, then show a shareable QR +
 * token the user can send to a friend. The sponsor then opens a QR scan
 * (routed to the `BuyForMe` screen) to pay from their wallet.
 *
 * Flow states:
 *   1. `idle`        — product summary + optional note + "Generate request"
 *   2. `creating`    — loading spinner while the API call is in flight
 *   3. `active`      — request created; show QR, share, cancel
 *   4. `error`       — show retry
 */
export default function RequestBuyForMe({ route, navigation }) {
  const { confirm, alert } = useDialog();
  const insets = useSafeAreaInsets();

  const initial   = route?.params?.product || null;
  const productId = route?.params?.productId || initial?.id;

  const [product, setProduct] = useState(initial);
  const [loading, setLoading] = useState(!initial);

  const [note, setNote] = useState('');
  const [status, setStatus] = useState('idle');    // idle | creating | active | error
  const [request, setRequest] = useState(null);
  const [errorMsg, setErrorMsg] = useState(null);

  // Directed-request (ExtraCash Number) lookup state.
  //   lookupInput — what the user has typed so far (any format)
  //   lookupState — 'idle' | 'searching' | 'found' | 'notfound'
  //   lookupUser  — resolved user record after a successful lookup
  const [lookupInput, setLookupInput] = useState('');
  const [lookupState, setLookupState] = useState('idle');
  const [lookupUser,  setLookupUser]  = useState(null);
  const [lookupError, setLookupError] = useState(null);

  /* ── Load product if we only received an ID ──────────────────────── */
  useEffect(() => {
    let cancelled = false;
    (async () => {
      if (product || !productId) return;
      try {
        const res = await api.get(`/products/${productId}`);
        if (!cancelled) setProduct(res.data);
      } catch {
        if (!cancelled) {
          await alert({
            title: 'Unable to load product',
            message: 'Please try again later.',
            tone: 'danger',
          });
          navigation.goBack();
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true; };
  }, [productId]);

  const generate = useCallback(async () => {
    if (!productId) return;
    setStatus('creating');
    setErrorMsg(null);
    try {
      const res = await api.post('/buy-requests', {
        product_id: productId,
        note: note.trim() || null,
        // Only send when the lookup completed successfully — otherwise the
        // backend creates an open (tokenised) request that anyone with the
        // QR / token can fulfil.
        target_extracash_number: lookupUser?.extracash_number || null,
      });
      setRequest(res.data);
      setStatus('active');
    } catch (err) {
      setErrorMsg(err?.response?.data?.message || 'Could not create the request.');
      setStatus('error');
    }
  }, [productId, note, lookupUser]);

  /**
   * Resolve the typed ExtraCash number to a user record. Any non-digit
   * characters are stripped server-side, so "EC-1234 5678" works too.
   */
  const runLookup = async () => {
    const trimmed = (lookupInput || '').trim();
    if (!trimmed) return;
    setLookupState('searching');
    setLookupError(null);
    setLookupUser(null);
    try {
      const res = await api.post('/users/lookup', {
        extracash_number: trimmed,
      });
      setLookupUser(res.data);
      setLookupState('found');
    } catch (err) {
      setLookupError(err?.response?.data?.message || 'We couldn\'t find that ExtraCash number.');
      setLookupState('notfound');
    }
  };

  const clearLookup = () => {
    setLookupInput('');
    setLookupUser(null);
    setLookupState('idle');
    setLookupError(null);
  };

  const shareRequest = async () => {
    if (!request || !product) return;
    try {
      await Share.share({
        title: `Help me buy ${product.title}`,
        message:
          `Hey — can you help me buy this on ExtraCash?\n\n` +
          `${product.title} — ${fmt(product.price)}\n\n` +
          `Open the ExtraCash app, tap Scan QR and scan the code I've saved, ` +
          `or paste this request token:\n${request.token}`,
      });
    } catch {
      // share sheet dismissed
    }
  };

  const copyToken = async () => {
    if (!request) return;
    try {
      Clipboard.setString(request.token);
      await alert({
        title: 'Token copied',
        message: 'Paste it in a message to your friend — they can enter it in the app to pay.',
        tone: 'success',
      });
    } catch {}
  };

  const cancelRequest = async () => {
    if (!request) return;
    const ok = await confirm({
      title: 'Cancel request?',
      message: 'The shared link and QR will stop working immediately.',
      tone: 'warn',
      confirmLabel: 'Cancel request',
      cancelLabel: 'Keep it',
    });
    if (!ok) return;
    try {
      await api.delete(`/buy-requests/${request.token}`);
      setRequest(null);
      setStatus('idle');
      setNote('');
    } catch (err) {
      await alert({
        title: 'Could not cancel',
        message: err?.response?.data?.message || 'Please try again.',
        tone: 'danger',
      });
    }
  };

  const openChatWithTarget = async () => {
    if (!request?.target?.id) return;
    try {
      const conversationId = await ensureDirectConversation(request.target.id);
      if (conversationId) {
        navigation.navigate('MessageThread', {
          conversationId,
          otherUser: { id: request.target.id, name: request.target.name, email: request.target.email },
          context: {
            type: 'buy_request',
            title: `Buy-for-Me request · ${product?.title || 'Item'}`,
          },
        });
      }
    } catch {}
  };

  const bottomPad = Math.max(insets.bottom, 16) + 14;
  const primaryLabel = status === 'error'
    ? 'Try again'
    : lookupUser
      ? `Send request to ${lookupUser.name.split(' ')[0]}`
      : 'Generate request';

  if (loading || !product) {
    return (
      <SafeAreaView style={styles.container}>
        <Header onBack={() => navigation.goBack()} />
        <View style={styles.loadingBox}>
          <ActivityIndicator size="large" color="#111827" />
          <Text style={styles.loadingText}>Loading product…</Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      <Header onBack={() => navigation.goBack()} />

      <ScrollView
        keyboardShouldPersistTaps="handled"
        contentContainerStyle={{ padding: 16, paddingBottom: bottomPad + 80, gap: 14 }}
      >
        {/* Hero: how it works (only while idle so the active state is focused) */}
        {status !== 'active' && (
          <View style={styles.howItWorks}>
            <View style={styles.hiwIcon}>
              <Feather name="gift" size={20} color="#fff" />
            </View>
            <View style={{ flex: 1 }}>
              <Text style={styles.hiwTitle}>How "Buy for Me" works</Text>
              <Text style={styles.hiwStep}>
                <Text style={styles.hiwBold}>1.</Text>  Generate a secure request for this item.
              </Text>
              <Text style={styles.hiwStep}>
                <Text style={styles.hiwBold}>2.</Text>  Share the link or QR with a friend.
              </Text>
              <Text style={styles.hiwStep}>
                <Text style={styles.hiwBold}>3.</Text>  They pay from their wallet. You get the item.
              </Text>
            </View>
          </View>
        )}

        {/* Product card — same shape sponsor will see */}
        <View style={styles.productCard}>
          <View style={styles.productImageBox}>
            {product.image_url ? (
              <Image source={{ uri: product.image_url }} style={styles.productImage} resizeMode="cover" />
            ) : (
              <Feather name="package" size={32} color="#9CA3AF" />
            )}
          </View>
          <View style={{ padding: 14, gap: 4 }}>
            {product.category ? (
              <Text style={styles.productCategory}>{product.category}</Text>
            ) : null}
            <Text style={styles.productTitle}>{product.title}</Text>
            <Text style={styles.productPrice}>{fmt(product.price)}</Text>
            {product.seller?.name ? (
              <Text style={styles.productSeller} numberOfLines={1}>Sold by {product.seller.name}</Text>
            ) : null}
          </View>
        </View>

        {/* Active request — QR + actions. Two visual modes:
            1. `directed` — we sent it to a specific user. Show a compact
               "sent" card; the QR is still available as a backup but the
               primary UX is trust-the-inbox.
            2. `open`     — no target selected. Show the big QR + token so
               the user can share with anyone. */}
        {status === 'active' && request ? (
          request.is_directed && request.target ? (
            <>
              <View style={styles.sentCard}>
                <View style={styles.sentIcon}>
                  <Feather name="send" size={20} color="#047857" />
                </View>
                <Text style={styles.sentTitle}>Request sent</Text>
                <Text style={styles.sentBody}>
                  <Text style={styles.sentName}>{request.target.name}</Text> now has this
                  in their Buy-for-Me inbox. You'll be notified the moment they pay.
                </Text>
                <View style={styles.sentMeta}>
                  <View style={styles.sentMetaRow}>
                    <Feather name="user" size={13} color="#6B7280" />
                    <Text style={styles.sentMetaText}>EC · {request.target.extracash_number}</Text>
                  </View>
                  {request.expires_at ? (
                    <View style={styles.sentMetaRow}>
                      <Feather name="clock" size={13} color="#6B7280" />
                      <Text style={styles.sentMetaText}>
                        Expires {new Date(request.expires_at).toLocaleString()}
                      </Text>
                    </View>
                  ) : null}
                </View>
              </View>

              <TouchableOpacity
                style={styles.messageTargetBtn}
                onPress={openChatWithTarget}
                activeOpacity={0.85}
              >
                <Feather name="message-circle" size={16} color="#fff" />
                <Text style={styles.messageTargetText}>Message {request.target.name.split(' ')[0]}</Text>
              </TouchableOpacity>

              <View style={styles.stepsCard}>
                <Text style={styles.stepsTitle}>What happens next</Text>
                <View style={styles.stepRow}>
                  <View style={styles.stepDot}><Text style={styles.stepNum}>1</Text></View>
                  <Text style={styles.stepText}>
                    {request.target.name.split(' ')[0]} opens ExtraCash and sees your request.
                  </Text>
                </View>
                <View style={styles.stepRow}>
                  <View style={styles.stepDot}><Text style={styles.stepNum}>2</Text></View>
                  <Text style={styles.stepText}>They confirm & pay {fmt(product.price)} from their wallet.</Text>
                </View>
                <View style={styles.stepRow}>
                  <View style={styles.stepDot}><Text style={styles.stepNum}>3</Text></View>
                  <Text style={styles.stepText}>We mark the purchase as theirs; the seller ships it to you.</Text>
                </View>
              </View>
            </>
          ) : (
            <>
              <View style={styles.qrPanel}>
                <View style={styles.qrCode}>
                  <QRCode
                    value={request.qr_payload}
                    size={200}
                    backgroundColor="#ffffff"
                    color="#111827"
                  />
                </View>
                <Text style={styles.qrHelp}>
                  Show this QR to a friend — they open the ExtraCash scanner and pay on your behalf.
                </Text>

                <View style={styles.tokenRow}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.tokenLabel}>Or share this token</Text>
                    <Text style={styles.tokenValue} numberOfLines={1}>
                      {request.token}
                    </Text>
                  </View>
                  <TouchableOpacity onPress={copyToken} style={styles.tokenCopyBtn} activeOpacity={0.85}>
                    <Feather name="copy" size={14} color="#111827" />
                    <Text style={styles.tokenCopyText}>Copy</Text>
                  </TouchableOpacity>
                </View>

                {request.expires_at ? (
                  <View style={styles.expiryPill}>
                    <Feather name="clock" size={12} color="#6B7280" />
                    <Text style={styles.expiryText}>
                      Expires {new Date(request.expires_at).toLocaleString()}
                    </Text>
                  </View>
                ) : null}
              </View>

              <View style={styles.stepsCard}>
                <Text style={styles.stepsTitle}>What happens next</Text>
                <View style={styles.stepRow}>
                  <View style={styles.stepDot}><Text style={styles.stepNum}>1</Text></View>
                  <Text style={styles.stepText}>Your friend scans the QR or pastes the token in ExtraCash.</Text>
                </View>
                <View style={styles.stepRow}>
                  <View style={styles.stepDot}><Text style={styles.stepNum}>2</Text></View>
                  <Text style={styles.stepText}>They confirm & pay {fmt(product.price)} from their wallet.</Text>
                </View>
                <View style={styles.stepRow}>
                  <View style={styles.stepDot}><Text style={styles.stepNum}>3</Text></View>
                  <Text style={styles.stepText}>We mark the purchase as theirs; the seller ships it to you.</Text>
                </View>
              </View>
            </>
          )
        ) : null}

        {/* Idle / create form */}
        {status !== 'active' ? (
          <>
            {/* ── Target lookup (directed request) ────────────────
                The ExtraCash Number is a public handle — safe to type
                and share. When found, the request is sent *to that
                specific user* instead of being an open sharable link. */}
            <View style={styles.lookupCard}>
              <Text style={styles.lookupLabel}>Send to a friend (optional)</Text>
              <Text style={styles.lookupHint}>
                Enter their ExtraCash number to send the request straight to their inbox.
                Leave blank to just generate a shareable link.
              </Text>

              {lookupUser ? (
                // ── Resolved user card ─────────────────────────────
                <View style={styles.resolvedBox}>
                  <View style={styles.resolvedAvatar}>
                    {lookupUser.profile_photo_url ? (
                      <Image source={{ uri: lookupUser.profile_photo_url }} style={styles.resolvedAvatarImg} />
                    ) : (
                      <Text style={styles.resolvedInitials}>
                        {lookupUser.name?.split(' ').map(w => w[0]).join('').slice(0,2).toUpperCase() || 'U'}
                      </Text>
                    )}
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.resolvedName} numberOfLines={1}>{lookupUser.name}</Text>
                    <Text style={styles.resolvedNumber}>EC · {lookupUser.extracash_number}</Text>
                  </View>
                  <TouchableOpacity onPress={clearLookup} style={styles.resolvedClear} hitSlop={{ top: 8, right: 8, bottom: 8, left: 8 }}>
                    <Feather name="x" size={16} color="#6B7280" />
                  </TouchableOpacity>
                </View>
              ) : (
                // ── Input + Lookup button ──────────────────────────
                <>
                  <View style={styles.lookupRow}>
                    <View style={styles.lookupPrefix}>
                      <Text style={styles.lookupPrefixText}>EC</Text>
                    </View>
                    <TextInput
                      value={lookupInput}
                      onChangeText={(t) => { setLookupInput(t); if (lookupState !== 'idle') setLookupState('idle'); }}
                      placeholder="e.g. 12345678"
                      placeholderTextColor="#9CA3AF"
                      keyboardType="number-pad"
                      maxLength={16}
                      style={styles.lookupInput}
                      onSubmitEditing={runLookup}
                      returnKeyType="search"
                    />
                    <TouchableOpacity
                      style={[
                        styles.lookupBtn,
                        (!lookupInput.trim() || lookupState === 'searching') && styles.lookupBtnDisabled,
                      ]}
                      onPress={runLookup}
                      disabled={!lookupInput.trim() || lookupState === 'searching'}
                      activeOpacity={0.85}
                    >
                      {lookupState === 'searching' ? (
                        <ActivityIndicator color="#fff" size="small" />
                      ) : (
                        <Text style={styles.lookupBtnText}>Lookup</Text>
                      )}
                    </TouchableOpacity>
                  </View>

                  {lookupState === 'notfound' && lookupError ? (
                    <View style={styles.lookupErrorRow}>
                      <Feather name="alert-circle" size={13} color="#B45309" />
                      <Text style={styles.lookupErrorText}>{lookupError}</Text>
                    </View>
                  ) : null}
                </>
              )}
            </View>

            <View style={styles.noteCard}>
              <Text style={styles.noteLabel}>Add a note (optional)</Text>
              <Text style={styles.noteHint}>
                Let {lookupUser ? (lookupUser.name.split(' ')[0] || 'them') : 'your friend'} know
                why you're asking. They'll see it before paying.
              </Text>
              <TextInput
                value={note}
                onChangeText={setNote}
                placeholder="e.g. Could you grab this? I'll pay you back on Friday."
                placeholderTextColor="#9CA3AF"
                multiline
                maxLength={280}
                style={styles.noteInput}
              />
              <Text style={styles.noteCount}>{note.length} / 280</Text>
            </View>

            {status === 'error' && errorMsg ? (
              <View style={styles.errorCard}>
                <Feather name="alert-triangle" size={16} color="#DC2626" />
                <Text style={styles.errorText}>{errorMsg}</Text>
              </View>
            ) : null}
          </>
        ) : null}
      </ScrollView>

      {/* Sticky footer action */}
      <View style={[styles.footer, { paddingBottom: bottomPad }]}>
        {status === 'active' ? (
          <View style={styles.footerRow}>
            <TouchableOpacity
              style={styles.secondaryBtn}
              onPress={cancelRequest}
              activeOpacity={0.85}
            >
              <Feather name="trash-2" size={15} color="#DC2626" />
              <Text style={styles.secondaryBtnText}>Cancel</Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={styles.primaryBtn}
              onPress={request?.is_directed ? () => navigation.goBack() : shareRequest}
              activeOpacity={0.85}
            >
              <Feather
                name={request?.is_directed ? 'check' : 'share-2'}
                size={16}
                color="#fff"
              />
              <Text style={styles.primaryBtnText}>
                {request?.is_directed ? 'Done' : 'Share request'}
              </Text>
            </TouchableOpacity>
          </View>
        ) : (
          <TouchableOpacity
            style={[styles.primaryBtn, status === 'creating' && { opacity: 0.8 }]}
            onPress={generate}
            disabled={status === 'creating'}
            activeOpacity={0.85}
          >
            {status === 'creating' ? (
              <>
                <ActivityIndicator color="#fff" size="small" />
                <Text style={styles.primaryBtnText}>
                  {lookupUser ? 'Sending…' : 'Generating…'}
                </Text>
              </>
            ) : (
              <>
                <Feather name={lookupUser ? 'send' : 'gift'} size={16} color="#fff" />
                <Text style={styles.primaryBtnText} numberOfLines={1}>
                  {primaryLabel}
                </Text>
              </>
            )}
          </TouchableOpacity>
        )}
      </View>
    </SafeAreaView>
  );
}

function Header({ onBack }) {
  return <ScreenHeader title="Buy for Me" onLeftPress={onBack} />;
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F9FAFB' },

  loadingBox: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
  },
  loadingText: { color: '#6B7280', fontSize: 13 },

  howItWorks: {
    flexDirection: 'row',
    gap: 12,
    backgroundColor: '#111827',
    padding: 14,
    borderRadius: 14,
  },
  hiwIcon: {
    width: 40, height: 40, borderRadius: 10,
    backgroundColor: 'rgba(255,255,255,0.15)',
    justifyContent: 'center', alignItems: 'center',
  },
  hiwTitle: { color: '#fff', fontWeight: '800', fontSize: 14, marginBottom: 6 },
  hiwStep:  { color: 'rgba(255,255,255,0.85)', fontSize: 12, lineHeight: 18 },
  hiwBold:  { color: '#fff', fontWeight: '800' },

  productCard: {
    backgroundColor: '#fff',
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    overflow: 'hidden',
  },
  productImageBox: {
    height: 160,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
  },
  productImage: { width: '100%', height: '100%' },
  productCategory: {
    fontSize: 10, fontWeight: '800',
    color: '#9CA3AF', textTransform: 'uppercase', letterSpacing: 0.5,
  },
  productTitle:  { fontSize: 16, fontWeight: '800', color: '#111827' },
  productPrice:  { fontSize: 18, fontWeight: '900', color: '#111827', marginTop: 2 },
  productSeller: { fontSize: 12, color: '#6B7280', marginTop: 2 },

  noteCard: {
    backgroundColor: '#fff',
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 14,
  },
  noteLabel: { fontSize: 13, fontWeight: '800', color: '#111827' },
  noteHint:  { fontSize: 11, color: '#6B7280', marginTop: 2, marginBottom: 10 },
  noteInput: {
    minHeight: 84,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    borderRadius: 10,
    padding: 12,
    fontSize: 14,
    color: '#111827',
    textAlignVertical: 'top',
    backgroundColor: '#F9FAFB',
  },
  noteCount: {
    fontSize: 11, color: '#9CA3AF',
    textAlign: 'right', marginTop: 6,
  },

  qrPanel: {
    backgroundColor: '#fff',
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 18,
    alignItems: 'center',
    gap: 12,
  },
  qrCode: {
    padding: 14,
    borderRadius: 12,
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  qrHelp: {
    fontSize: 12, color: '#6B7280',
    textAlign: 'center', lineHeight: 18,
    maxWidth: 260,
  },
  tokenRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: '#F9FAFB',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    alignSelf: 'stretch',
  },
  tokenLabel: { fontSize: 10, fontWeight: '800', color: '#6B7280', textTransform: 'uppercase', letterSpacing: 0.4 },
  tokenValue: { fontSize: 11, color: '#111827', fontFamily: 'Menlo', marginTop: 2 },
  tokenCopyBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#fff',
    paddingHorizontal: 10,
    paddingVertical: 8,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  tokenCopyText: { fontSize: 12, fontWeight: '800', color: '#111827' },

  expiryPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 12,
    paddingVertical: 5,
    backgroundColor: '#F3F4F6',
    borderRadius: 999,
  },
  expiryText: { fontSize: 11, fontWeight: '700', color: '#6B7280' },

  stepsCard: {
    backgroundColor: '#fff',
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 14,
    gap: 10,
  },
  messageTargetBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: '#111827',
    borderRadius: 12,
    paddingVertical: 14,
    paddingHorizontal: 14,
  },
  messageTargetText: { color: '#fff', fontWeight: '800', fontSize: 14 },
  stepsTitle: { fontSize: 13, fontWeight: '800', color: '#111827', marginBottom: 4 },
  stepRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
  },
  stepDot: {
    width: 22, height: 22, borderRadius: 11,
    backgroundColor: '#111827',
    justifyContent: 'center', alignItems: 'center',
  },
  stepNum: { color: '#fff', fontWeight: '800', fontSize: 11 },
  stepText: { flex: 1, fontSize: 13, color: '#374151', lineHeight: 18 },

  errorCard: {
    flexDirection: 'row',
    gap: 8,
    backgroundColor: '#FEF2F2',
    borderColor: '#FECACA',
    borderWidth: 1,
    borderRadius: 12,
    padding: 12,
    alignItems: 'center',
  },
  errorText: { flex: 1, color: '#991B1B', fontSize: 12, fontWeight: '700' },

  footer: {
    paddingHorizontal: 16,
    paddingTop: 12,
    backgroundColor: '#fff',
    borderTopWidth: 1,
    borderTopColor: '#E5E7EB',
  },
  footerRow: { flexDirection: 'row', gap: 10 },
  primaryBtn: {
    flex: 1,
    minHeight: 52,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: '#111827',
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderRadius: 12,
  },
  primaryBtnText: {
    color: '#fff',
    fontWeight: '800',
    fontSize: 14,
    lineHeight: 18,
    includeFontPadding: false,
    textAlign: 'center',
  },
  secondaryBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 14,
    paddingHorizontal: 16,
    borderRadius: 12,
    backgroundColor: '#FEF2F2',
    borderWidth: 1,
    borderColor: '#FECACA',
  },
  secondaryBtnText: { color: '#DC2626', fontWeight: '800', fontSize: 13 },

  /* ── Target lookup card ───────────────────────────────────── */
  lookupCard: {
    backgroundColor: '#fff',
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 14,
  },
  lookupLabel: { fontSize: 13, fontWeight: '800', color: '#111827' },
  lookupHint:  { fontSize: 11, color: '#6B7280', marginTop: 2, marginBottom: 10, lineHeight: 16 },

  lookupRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  lookupPrefix: {
    paddingHorizontal: 10,
    paddingVertical: 12,
    backgroundColor: '#F3F4F6',
    borderRadius: 10,
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  lookupPrefixText: { fontSize: 13, fontWeight: '800', color: '#6B7280', letterSpacing: 0.6 },
  lookupInput: {
    flex: 1,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 14,
    color: '#111827',
    backgroundColor: '#F9FAFB',
    letterSpacing: 1,
  },
  lookupBtn: {
    backgroundColor: '#111827',
    paddingHorizontal: 14,
    paddingVertical: 12,
    borderRadius: 10,
    minWidth: 74,
    alignItems: 'center',
    justifyContent: 'center',
  },
  lookupBtnDisabled: { backgroundColor: '#9CA3AF' },
  lookupBtnText: { color: '#fff', fontWeight: '800', fontSize: 13 },
  lookupErrorRow: {
    flexDirection: 'row',
    gap: 6,
    alignItems: 'center',
    marginTop: 10,
    padding: 10,
    borderRadius: 10,
    backgroundColor: '#FFFBEB',
    borderWidth: 1,
    borderColor: '#FDE68A',
  },
  lookupErrorText: { flex: 1, fontSize: 12, fontWeight: '700', color: '#92400E' },

  resolvedBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 10,
    borderRadius: 12,
    backgroundColor: '#ECFDF5',
    borderWidth: 1,
    borderColor: '#A7F3D0',
  },
  resolvedAvatar: {
    width: 40, height: 40, borderRadius: 20,
    backgroundColor: '#047857',
    justifyContent: 'center', alignItems: 'center',
    overflow: 'hidden',
  },
  resolvedAvatarImg: { width: '100%', height: '100%' },
  resolvedInitials: { color: '#fff', fontWeight: '800', fontSize: 14 },
  resolvedName:   { fontSize: 14, fontWeight: '800', color: '#065F46' },
  resolvedNumber: { fontSize: 11, fontWeight: '700', color: '#047857', marginTop: 1, letterSpacing: 0.4 },
  resolvedClear: {
    width: 28, height: 28, borderRadius: 14,
    backgroundColor: '#fff',
    justifyContent: 'center', alignItems: 'center',
  },

  /* ── "Sent" confirmation (directed request) ───────────────── */
  sentCard: {
    backgroundColor: '#fff',
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#A7F3D0',
    padding: 18,
    alignItems: 'center',
    gap: 8,
  },
  sentIcon: {
    width: 48, height: 48, borderRadius: 24,
    backgroundColor: '#ECFDF5',
    justifyContent: 'center', alignItems: 'center',
    marginBottom: 2,
  },
  sentTitle: { fontSize: 16, fontWeight: '900', color: '#065F46' },
  sentBody:  { fontSize: 13, color: '#374151', textAlign: 'center', lineHeight: 19 },
  sentName:  { fontWeight: '800', color: '#111827' },
  sentMeta:  { alignSelf: 'stretch', marginTop: 6, gap: 6 },
  sentMetaRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#F9FAFB',
    paddingHorizontal: 10,
    paddingVertical: 8,
    borderRadius: 10,
  },
  sentMetaText: { fontSize: 12, fontWeight: '700', color: '#6B7280' },
});
