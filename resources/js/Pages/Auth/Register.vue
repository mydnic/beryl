<template>
    <div>
        <UCard class="w-full">
            <template #header>
                Create account
            </template>
            <form
                class="space-y-6"
                @submit.prevent="form.post('/register')"
            >
                <UAlert
                    v-if="$page.props.status"
                    :description="$page.props.status"
                />

                <UAlert
                    v-if="Object.keys($page.props.errors).length"
                    color="error"
                    :description="Object.values($page.props.errors).join(', ')"
                />

                <UFormField
                    label="Username"
                    required
                >
                    <UInput
                        v-model="form.name"
                        class="w-full"
                        placeholder="Username"
                    />
                </UFormField>

                <UFormField
                    label="Email"
                    required
                >
                    <UInput
                        v-model="form.email"
                        class="w-full"
                        type="email"
                        placeholder="Email"
                    />
                </UFormField>

                <UFormField
                    label="Password"
                    required
                >
                    <UInput
                        v-model="form.password"
                        class="w-full"
                        type="password"
                        placeholder="Password"
                    />
                </UFormField>

                <UFormField
                    label="Password Confirmation"
                    required
                >
                    <UInput
                        v-model="form.password_confirmation"
                        class="w-full"
                        type="password"
                        placeholder="Confirm Password"
                    />
                </UFormField>

                <div class="text-center">
                    <UButton
                        type="submit"
                        label="Create account"
                        :disabled="form.processing"
                        :class="{ loading: form.processing }"
                    />
                </div>
            </form>
        </UCard>

        <p class="text-center text-sm leading-6 mt-10 text-gray-400">
            Already have an account ?
            <Link
                href="/login"
                class="font-semibold text-primary-500 hover:text-primary-400"
            >
                Login
            </Link>
        </p>
    </div>
</template>

<script>
import GuestLayout from '../../Layouts/GuestLayout.vue'

export default {
    layout: GuestLayout,
    data () {
        return {
            form: this.$inertia.form({
                name: '',
                email: '',
                password: '',
                password_confirmation: ''
            })
        }
    }
}
</script>
