<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletLedger;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    protected function lockWallet(Wallet $wallet): Wallet
    {
        return Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
    }

    /**
     * Lock two wallets in deterministic order to avoid deadlocks.
     *
     * @return array{0: Wallet, 1: Wallet}
     */
    protected function lockWalletPair(Wallet $fromWallet, Wallet $toWallet): array
    {
        if ($fromWallet->id === $toWallet->id) {
            throw new \RuntimeException('Cannot transfer to the same wallet.');
        }

        $ids = [$fromWallet->id, $toWallet->id];
        sort($ids);

        $wallets = Wallet::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        return [
            $wallets[$fromWallet->id],
            $wallets[$toWallet->id],
        ];
    }

    protected function createLedgerEntry(array $attributes): WalletLedger
    {
        return WalletLedger::create($attributes);
    }

    /**
     * Credit the wallet's available balance and record a ledger entry.
     */
    public function credit(
        Wallet $wallet,
        string $type,
        float $amount,
        string $referenceType,
        int $referenceId,
        array $meta = []
    ): WalletLedger {
        return DB::transaction(function () use ($wallet, $type, $amount, $referenceType, $referenceId, $meta) {
            $wallet = $this->lockWallet($wallet);

            $balanceBefore = (float) $wallet->available_balance;
            $wallet->increment('available_balance', $amount);
            $balanceAfter = (float) $wallet->fresh()->available_balance;

            return $this->createLedgerEntry([
                'user_id' => $wallet->user_id,
                'wallet_id' => $wallet->id,
                'type' => $type,
                'direction' => 'credit',
                'amount' => $amount,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'metadata' => $meta,
            ]);
        });
    }

    /**
     * Debit the wallet's available balance and record a ledger entry.
     *
     * @throws \RuntimeException if insufficient funds
     */
    public function debit(
        Wallet $wallet,
        string $type,
        float $amount,
        string $referenceType,
        int $referenceId,
        array $meta = []
    ): WalletLedger {
        return DB::transaction(function () use ($wallet, $type, $amount, $referenceType, $referenceId, $meta) {
            $wallet = $this->lockWallet($wallet);

            if ((float) $wallet->available_balance < $amount) {
                throw new \RuntimeException('Insufficient available balance.');
            }

            $balanceBefore = (float) $wallet->available_balance;
            $wallet->decrement('available_balance', $amount);
            $balanceAfter = (float) $wallet->fresh()->available_balance;

            return $this->createLedgerEntry([
                'user_id' => $wallet->user_id,
                'wallet_id' => $wallet->id,
                'type' => $type,
                'direction' => 'debit',
                'amount' => $amount,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'metadata' => $meta,
            ]);
        });
    }

    /**
     * Move amount into pending balance (cashback hold).
     */
    public function creditPending(
        Wallet $wallet,
        string $type,
        float $amount,
        string $referenceType,
        int $referenceId,
        array $meta = []
    ): WalletLedger {
        return DB::transaction(function () use ($wallet, $type, $amount, $referenceType, $referenceId, $meta) {
            $wallet = $this->lockWallet($wallet);

            $balanceBefore = (float) $wallet->pending_balance;
            $wallet->increment('pending_balance', $amount);
            $balanceAfter = (float) $wallet->fresh()->pending_balance;

            return $this->createLedgerEntry([
                'user_id' => $wallet->user_id,
                'wallet_id' => $wallet->id,
                'type' => $type,
                'direction' => 'credit_pending',
                'amount' => $amount,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'metadata' => $meta,
            ]);
        });
    }

    /**
     * Release a held amount from pending → available balance.
     *
     * @throws \RuntimeException if insufficient pending balance
     */
    public function releasePending(
        Wallet $wallet,
        float $amount,
        string $referenceType,
        int $referenceId,
        array $meta = []
    ): WalletLedger {
        return DB::transaction(function () use ($wallet, $amount, $referenceType, $referenceId, $meta) {
            $wallet = $this->lockWallet($wallet);

            if ((float) $wallet->pending_balance < $amount) {
                throw new \RuntimeException('Insufficient pending balance to release.');
            }

            $wallet->decrement('pending_balance', $amount);
            $wallet->increment('available_balance', $amount);
            $wallet->increment('lifetime_cashback_earned', $amount);

            $wallet->refresh();

            return $this->createLedgerEntry([
                'user_id' => $wallet->user_id,
                'wallet_id' => $wallet->id,
                'type' => 'cashback_release',
                'direction' => 'credit',
                'amount' => $amount,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'balance_before' => (float) $wallet->available_balance - $amount,
                'balance_after' => (float) $wallet->available_balance,
                'metadata' => $meta,
            ]);
        });
    }

    /**
     * Atomically move funds between two wallets and write both ledger rows.
     *
     * @return array{debit: WalletLedger, credit: WalletLedger}
     *
     * @throws \RuntimeException if the sender lacks funds or the wallets are identical
     */
    public function transfer(
        Wallet $fromWallet,
        Wallet $toWallet,
        float $amount,
        string $referenceType,
        int $debitReferenceId,
        int $creditReferenceId,
        string $debitType = 'transfer_send',
        string $creditType = 'transfer_receive',
        array $debitMeta = [],
        array $creditMeta = []
    ): array {
        return DB::transaction(function () use (
            $fromWallet,
            $toWallet,
            $amount,
            $referenceType,
            $debitReferenceId,
            $creditReferenceId,
            $debitType,
            $creditType,
            $debitMeta,
            $creditMeta
        ) {
            [$senderWallet, $recipientWallet] = $this->lockWalletPair($fromWallet, $toWallet);

            if ((float) $senderWallet->available_balance < $amount) {
                throw new \RuntimeException('Insufficient available balance.');
            }

            $senderBefore = (float) $senderWallet->available_balance;
            $recipientBefore = (float) $recipientWallet->available_balance;

            $senderWallet->decrement('available_balance', $amount);
            $recipientWallet->increment('available_balance', $amount);

            $senderWallet->refresh();
            $recipientWallet->refresh();

            $debitLedger = $this->createLedgerEntry([
                'user_id' => $senderWallet->user_id,
                'wallet_id' => $senderWallet->id,
                'type' => $debitType,
                'direction' => 'debit',
                'amount' => $amount,
                'reference_type' => $referenceType,
                'reference_id' => $debitReferenceId,
                'balance_before' => $senderBefore,
                'balance_after' => (float) $senderWallet->available_balance,
                'metadata' => $debitMeta,
            ]);

            $creditLedger = $this->createLedgerEntry([
                'user_id' => $recipientWallet->user_id,
                'wallet_id' => $recipientWallet->id,
                'type' => $creditType,
                'direction' => 'credit',
                'amount' => $amount,
                'reference_type' => $referenceType,
                'reference_id' => $creditReferenceId,
                'balance_before' => $recipientBefore,
                'balance_after' => (float) $recipientWallet->available_balance,
                'metadata' => $creditMeta,
            ]);

            return [
                'debit' => $debitLedger,
                'credit' => $creditLedger,
            ];
        });
    }
}
