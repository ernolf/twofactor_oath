<!--
  - SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!--
  A native <select> rendered with the exact visual language of @nextcloud/vue's
  NcTextField (outlined box, box-shadow border, floating label). Use it where a
  dropdown should sit seamlessly next to NcTextField inputs.

  It is intentionally self-contained: it only relies on Nextcloud's global CSS
  variables, so it is portable to any Nextcloud project without extra deps.

  By default the control auto-sizes its width to the wider of its floating label
  and its widest option (plus the input padding), down to a configurable minimum
  — so the notched label is never clipped, in any language. Set :auto-width="false"
  to size it via CSS instead.

  Props:
    model-value  the currently selected value (String or Number; type is preserved)
    options      array of { value, label } objects, or of plain primitives
    label        the floating label text
    disabled     disable the control
    auto-width   measure + fit the width to label/options (default true)
    min-width    minimum width in px when auto-sizing (default 56)
-->

<template>
	<div
		class="input-field input-field--trailing-icon"
		:class="{ 'input-field--disabled': disabled }"
		:style="{ width: widthStyle }">
		<div class="input-field__main-wrapper">
			<select
				:id="inputId"
				ref="select"
				class="input-field__input"
				:value="String(modelValue)"
				:disabled="disabled"
				@change="onChange">
				<option
					v-for="opt in normalizedOptions"
					:key="opt.key"
					:value="opt.key">
					{{ opt.label }}
				</option>
			</select>
			<label v-if="label" :for="inputId" class="input-field__label">
				{{ label }}
			</label>
			<div class="input-field__icon input-field__icon--trailing" aria-hidden="true">
				<svg width="20" height="20" viewBox="0 0 24 24">
					<path fill="currentColor" d="M7.41,8.58L12,13.17L16.59,8.58L18,10L12,16L6,10L7.41,8.58Z" />
				</svg>
			</div>
		</div>
	</div>
</template>

<script>
let uid = 0

// One shared offscreen canvas to measure text width in the control's own font.
let measureCanvas = null
/**
 * Measure the rendered width of a text string in a given CSS font.
 *
 * @param {string} text the text to measure
 * @param {string} font a CSS font shorthand value
 * @return {number} the measured width in pixels
 */
function measureText(text, font) {
	if (measureCanvas === null) {
		measureCanvas = document.createElement('canvas')
	}
	const ctx = measureCanvas.getContext('2d')
	ctx.font = font
	return ctx.measureText(text).width
}

export default {
	name: 'SelectField',
	props: {
		modelValue: {
			type: [String, Number],
			default: '',
		},

		options: {
			type: Array,
			required: true,
		},

		label: {
			type: String,
			default: '',
		},

		disabled: {
			type: Boolean,
			default: false,
		},

		autoWidth: {
			type: Boolean,
			default: true,
		},

		minWidth: {
			type: Number,
			default: 56,
		},
	},

	emits: ['update:modelValue'],
	data() {
		return {
			inputId: `select-field-${++uid}`,
			measuredWidth: null,
		}
	},

	computed: {
		// Accept both [{ value, label }] and plain primitives; the <option> value
		// is always the stringified value, so the original type is restored on change.
		normalizedOptions() {
			return this.options.map((opt) => {
				const isObject = opt !== null && typeof opt === 'object'
				const value = isObject ? opt.value : opt
				const label = isObject ? opt.label : String(opt)
				return { value, label, key: String(value) }
			})
		},

		widthStyle() {
			if (!this.autoWidth) {
				return null
			}
			return `${this.measuredWidth ?? this.minWidth}px`
		},
	},

	watch: {
		options() {
			this.$nextTick(() => this.measure())
		},

		label() {
			this.$nextTick(() => this.measure())
		},
	},

	mounted() {
		this.$nextTick(() => this.measure())
		// Re-measure once webfonts are ready (their metrics differ from fallbacks).
		if (window.document?.fonts?.ready) {
			window.document.fonts.ready.then(() => this.measure())
		}
	},

	methods: {
		onChange(event) {
			const selected = this.normalizedOptions.find((opt) => opt.key === event.target.value)
			this.$emit('update:modelValue', selected ? selected.value : event.target.value)
		},

		// Fit the width to the wider of the floating label and the widest option,
		// plus the input's horizontal padding (which reserves the chevron space).
		measure() {
			if (!this.autoWidth) {
				return
			}
			const select = this.$refs.select
			if (!select) {
				return
			}
			const cs = window.getComputedStyle(select)
			const family = cs.fontFamily || 'sans-serif'
			const optionFont = `${cs.fontWeight} ${cs.fontSize} ${family}`
			let content = 0
			for (const opt of this.normalizedOptions) {
				content = Math.max(content, measureText(opt.label, optionFont))
			}
			if (this.label) {
				// The floating label renders at 13px / weight 500 with a small padding.
				content = Math.max(content, measureText(this.label, `500 13px ${family}`) + 8)
			}
			const padL = parseFloat(cs.paddingLeft) || 0
			const padR = parseFloat(cs.paddingRight) || 0
			const total = Math.ceil(content + padL + padR + 4)
			this.measuredWidth = Math.max(total, this.minWidth)
		},
	},
}
</script>

<style scoped>
/*
 * Mirrors @nextcloud/vue NcInputField (NC 32+ box-shadow border variant).
 * Kept in sync with the upstream component's structure and CSS variables.
 */
.input-field {
	--input-border-color: var(--color-border-maxcontrast);
	--input-border-radius: var(--border-radius-element);
	--input-padding-start: var(--border-radius-element);
	--input-padding-end: var(--border-radius-element);
	position: relative;
	width: auto;
	margin-block-start: 6px;
}

.input-field--trailing-icon {
	--input-padding-end: calc(var(--default-clickable-area) - var(--default-grid-baseline));
}

.input-field--disabled {
	opacity: 0.4;
	filter: saturate(0.4);
}

.input-field__main-wrapper {
	height: var(--default-clickable-area);
	padding: var(--border-width-input-focused, 2px);
	position: relative;
}

.input-field__input {
	--input-border-box-shadow-light: 0 -1px var(--input-border-color),
		0 0 0 1px color-mix(in srgb, var(--input-border-color), 65% transparent);
	--input-border-box-shadow-dark: 0 1px var(--input-border-color),
		0 0 0 1px color-mix(in srgb, var(--input-border-color), 65% transparent);
	--input-border-box-shadow: var(--input-border-box-shadow-light);
	appearance: none;
	border: none;
	box-shadow: var(--input-border-box-shadow);
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	border-radius: var(--input-border-radius);
	cursor: pointer;
	font-size: var(--default-font-size);
	text-overflow: ellipsis;
	padding-block: 0;
	padding-inline: var(--input-padding-start) var(--input-padding-end);
	height: 100% !important;
	min-height: unset;
	width: 100%;
}

[data-theme-light] .input-field__input {
	--input-border-box-shadow: var(--input-border-box-shadow-light);
}

[data-theme-dark] .input-field__input {
	--input-border-box-shadow: var(--input-border-box-shadow-dark);
}

@media (prefers-color-scheme: dark) {
	.input-field__input {
		--input-border-box-shadow: var(--input-border-box-shadow-dark);
	}
}

/* A native <select> must stay a pointer in every state (some browsers show a
   text caret on a focused appearance:none select). */
.input-field__input,
.input-field__input:hover,
.input-field__input:focus,
.input-field__input:active {
	cursor: pointer;
}

.input-field__input:hover:not(:disabled) {
	box-shadow: 0 0 0 1px var(--input-border-color);
}

.input-field__input:focus:not(:disabled),
.input-field__input:active:not(:disabled) {
	--input-border-color: var(--color-main-text);
	box-shadow: 0 0 0 2px var(--input-border-color), 0 0 0 4px var(--color-main-background) !important;
}

.input-field__label {
	--input-label-font-size: 13px;
	font-size: var(--input-label-font-size);
	position: absolute;
	line-height: 1.5;
	inset-block-start: calc(-1.5 * var(--input-label-font-size) / 2);
	inset-inline: var(--border-width-input-focused, 2px);
	max-width: fit-content;
	font-weight: var(--font-weight-element, 500);
	border-radius: var(--default-grid-baseline) var(--default-grid-baseline) 0 0;
	background-color: var(--color-main-background);
	color: var(--color-text-maxcontrast);
	padding-inline: var(--default-grid-baseline);
	margin-inline: calc(var(--input-padding-start) - var(--default-grid-baseline)) calc(var(--input-padding-end) - var(--default-grid-baseline));
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	pointer-events: none;
}

.input-field__input:focus + .input-field__label {
	color: var(--color-main-text);
}

.input-field__icon {
	position: absolute;
	height: var(--default-clickable-area);
	width: var(--default-clickable-area);
	display: flex;
	align-items: center;
	justify-content: center;
	opacity: 0.7;
	inset-block-end: 0;
	pointer-events: none;
}

.input-field__icon--trailing {
	inset-inline-end: 0;
}
</style>
