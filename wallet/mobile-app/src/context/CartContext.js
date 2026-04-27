import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import api from '../services/client';
import { useAuth } from './AuthContext';

const CartContext = createContext(null);

const emptyTotals = {
  item_count: 0,
  line_count: 0,
  gross: 0,
  cashback: 0,
  admin_fee: 0,
  net_after_cashback: 0,
};

/**
 * Global cart state.
 * - Mirrors the server cart (`GET /cart`) and keeps item counts + totals handy for badges and screens.
 * - Mutations (add/update/remove/clear) hit the API and replace local state with the fresh server payload.
 */
export function CartProvider({ children }) {
  const { user } = useAuth();
  const [items, setItems] = useState([]);
  const [totals, setTotals] = useState(emptyTotals);
  const [loading, setLoading] = useState(false);
  const [mutating, setMutating] = useState(false);
  const [error, setError] = useState(null);

  const applyPayload = useCallback((payload) => {
    setItems(Array.isArray(payload?.items) ? payload.items : []);
    setTotals(payload?.totals || emptyTotals);
  }, []);

  const refresh = useCallback(async () => {
    if (!user) {
      setItems([]);
      setTotals(emptyTotals);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await api.get('/cart');
      applyPayload(res.data);
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load cart.');
    } finally {
      setLoading(false);
    }
  }, [user, applyPayload]);

  useEffect(() => { refresh(); }, [refresh]);

  const addItem = useCallback(async (productId, quantity = 1) => {
    setMutating(true);
    setError(null);
    try {
      const res = await api.post('/cart', { product_id: productId, quantity });
      applyPayload(res.data);
      return { ok: true, message: res.data?.message };
    } catch (err) {
      const message = err?.response?.data?.message || 'Could not add to cart.';
      setError(message);
      return { ok: false, message };
    } finally {
      setMutating(false);
    }
  }, [applyPayload]);

  const updateQuantity = useCallback(async (itemId, quantity) => {
    setMutating(true);
    setError(null);
    try {
      const res = await api.patch(`/cart/${itemId}`, { quantity });
      applyPayload(res.data);
      return { ok: true };
    } catch (err) {
      const message = err?.response?.data?.message || 'Could not update cart.';
      setError(message);
      return { ok: false, message };
    } finally {
      setMutating(false);
    }
  }, [applyPayload]);

  const removeItem = useCallback(async (itemId) => {
    setMutating(true);
    setError(null);
    try {
      const res = await api.delete(`/cart/${itemId}`);
      applyPayload(res.data);
      return { ok: true };
    } catch (err) {
      const message = err?.response?.data?.message || 'Could not remove item.';
      setError(message);
      return { ok: false, message };
    } finally {
      setMutating(false);
    }
  }, [applyPayload]);

  const clear = useCallback(async () => {
    setMutating(true);
    setError(null);
    try {
      const res = await api.delete('/cart');
      applyPayload(res.data);
      return { ok: true };
    } catch (err) {
      const message = err?.response?.data?.message || 'Could not clear cart.';
      setError(message);
      return { ok: false, message };
    } finally {
      setMutating(false);
    }
  }, [applyPayload]);

  const checkout = useCallback(async () => {
    setMutating(true);
    setError(null);
    try {
      const res = await api.post('/checkout');
      applyPayload({ items: [], totals: emptyTotals });
      return { ok: true, order: res.data?.order, message: res.data?.message };
    } catch (err) {
      const message = err?.response?.data?.message || 'Checkout failed.';
      setError(message);
      return { ok: false, message };
    } finally {
      setMutating(false);
    }
  }, [applyPayload]);

  const value = useMemo(() => ({
    items,
    totals,
    itemCount: totals.item_count,
    loading,
    mutating,
    error,
    refresh,
    addItem,
    updateQuantity,
    removeItem,
    clear,
    checkout,
  }), [items, totals, loading, mutating, error, refresh, addItem, updateQuantity, removeItem, clear, checkout]);

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export const useCart = () => {
  const ctx = useContext(CartContext);
  if (!ctx) {
    // Soft fallback so screens rendered outside the provider don't crash.
    return {
      items: [],
      totals: emptyTotals,
      itemCount: 0,
      loading: false,
      mutating: false,
      error: null,
      refresh: async () => {},
      addItem: async () => ({ ok: false, message: 'Cart not ready' }),
      updateQuantity: async () => ({ ok: false, message: 'Cart not ready' }),
      removeItem: async () => ({ ok: false, message: 'Cart not ready' }),
      clear: async () => ({ ok: false, message: 'Cart not ready' }),
      checkout: async () => ({ ok: false, message: 'Cart not ready' }),
    };
  }
  return ctx;
};
