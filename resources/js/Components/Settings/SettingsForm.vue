<template>
    <UForm
        :state="form"
        @submit.prevent="submit"
    >
        <UFormField
            label="Rename file when updating metadata"
            description="If enabled, files will be renamed to 'Artist - Title.ext' after applying metadata."
        >
            <USwitch v-model="form.rename_on_apply" />
        </UFormField>

        <div class="flex justify-end gap-2 mt-6">
            <UButton
                color="gray"
                variant="soft"
                @click="$emit('cancel')"
            >
                Cancel
            </UButton>
            <UButton
                type="submit"
                :loading="form.processing"
                icon="i-heroicons-check"
            >
                Save
            </UButton>
        </div>
    </UForm>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    initial: {
        type: Object,
        default: () => ({ rename_on_apply: false })
    }
})

const emit = defineEmits(['saved', 'cancel'])

const form = useForm({
    rename_on_apply: !!props.initial?.rename_on_apply
})

function submit () {
    form.post('/settings', {
        preserveScroll: true,
        onSuccess: () => emit('saved')
    })
}
</script>
