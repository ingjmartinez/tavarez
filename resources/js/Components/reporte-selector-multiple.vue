<script>
export default {
  props: {
    id: { type: String, required: true },
    label: { type: String, required: true },
    options: { type: Array, required: true },
    modelValue: { type: Array, required: true },
  },
  emits: ["update:modelValue"],
  mounted() {
    document.addEventListener("pointerdown", this.closeOnOutsideClick);
  },
  beforeUnmount() {
    document.removeEventListener("pointerdown", this.closeOnOutsideClick);
  },
  methods: {
    closeOnOutsideClick(event) {
      if (!this.$refs.selector?.contains(event.target)) this.$refs.selector?.removeAttribute("open");
    },
    selectAll() {
      this.$emit("update:modelValue", []);
    },
    toggle(option) {
      this.$emit("update:modelValue", this.modelValue.includes(option)
        ? this.modelValue.filter((value) => value !== option)
        : [...this.modelValue, option]);
    },
  },
};
</script>

<template>
  <div>
    <label :for="id" class="form-label">{{ label }}</label>
    <details ref="selector" class="position-relative">
      <summary :id="id" class="form-select text-truncate" style="cursor: pointer; list-style: none">
        {{ modelValue.length ? modelValue.join(", ") : "Todos" }}
      </summary>
      <div class="position-absolute w-100 bg-white border rounded shadow-sm p-2 overflow-auto" style="z-index: 10; max-height: 15rem">
        <label class="d-flex align-items-center gap-2 py-1">
          <input type="checkbox" :checked="!modelValue.length" @change="selectAll" /> Todos
        </label>
        <label v-for="option in options" :key="option" class="d-flex align-items-center gap-2 py-1">
          <input type="checkbox" :checked="modelValue.includes(option)" @change="toggle(option)" /> {{ option }}
        </label>
      </div>
    </details>
  </div>
</template>
