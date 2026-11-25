<div class="grid grid-cols-2 gap-3">
    <div>
        <label class="block text-xs font-bold uppercase mb-1">Paid on *</label>
        <input type="date" name="paid_on" required max="{{ $today }}" value="{{ $today }}"
            class="w-full text-xs border rounded-lg p-2.5">
    </div>
    <div>
        <label class="block text-xs font-bold uppercase mb-1">Mode *</label>
        <select name="payment_mode" required class="w-full text-xs border rounded-lg p-2.5">
            @foreach (\App\Support\Money::MODES as $v => $l)
                <option value="{{ $v }}" @selected($v === 'bank')>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-span-2">
        <label class="block text-xs font-bold uppercase mb-1">Reference no. (voucher, bank txn id)</label>
        <input type="text" name="reference_no" maxlength="60" class="w-full text-xs border rounded-lg p-2.5">
    </div>
</div>
