<template>
    <div>
        <UCard class="w-full">
            <template #header>
                Connectez-vous à votre compte
            </template>
            <form
                class="space-y-6"
                @submit.prevent="form.post('/login')"
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

                <UFormField>
                    <UCheckbox
                        v-model="form.remember"
                        label="Se souvenir de moi"
                    />
                </UFormField>

                <div class="text-center">
                    <UButton
                        type="submit"
                        label="Sign in"
                        :disabled="form.processing"
                        :class="{ loading: form.processing }"
                    />
                </div>
            </form>
        </UCard>

        <p class="text-center text-sm leading-6 mt-10 text-gray-500">
            Don't have an account?
            <Link
                href="/register"
                class="font-semibold text-primary-600 hover:text-primary-500"
            >
                Sign up
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
                email: '',
                password: '',
                remember: false
            })
        }
    }
}
</script>
