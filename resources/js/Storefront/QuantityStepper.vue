<script setup>
import { computed } from 'vue';

/**
 * Quantity control.
 *
 * A bare number input hides its own ceiling: the shopper types 9, submits, and
 * is told afterwards that the limit is 3. Here the button that would break the
 * cap is disabled, and the reason is one line underneath. The cap is still
 * enforced server-side — this only stops the customer finding out the hard way.
 */
const props = defineProps({
    modelValue: { type: Number, required: true },
    min: { type: Number, default: 1 },
    max: { type: Number, default: 99 },
    label: { type: String, default: 'Quantity' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const atFloor = computed(() => props.modelValue <= props.min);
const atCeiling = computed(() => props.modelValue >= props.max);

function set(value) {
    const clamped = Math.min(props.max, Math.max(props.min, Number.isFinite(value) ? value : props.min));

    emit('update:modelValue', clamped);
}
</script>

<template>
    <div class="qty-stepper">
        <button type="button" :aria-label="`Decrease ${label.toLowerCase()}`" :disabled="disabled || atFloor"
            @click="set(modelValue - 1)">&minus;</button>

        <input type="number" :value="modelValue" :min="min" :max="max" :aria-label="label" :disabled="disabled"
            inputmode="numeric" @change="set(Number($event.target.value))">

        <button type="button" :aria-label="`Increase ${label.toLowerCase()}`" :disabled="disabled || atCeiling"
            @click="set(modelValue + 1)">+</button>
    </div>
</template>
