<template>
    <Head title="Forgot Password - Beryl" />
    <div>
        <Card>
            <template #title>
                Connectez-vous à votre compte
            </template>
            <template #content>
                <Message
                    v-if="$page.props.status"
                    severity="success"
                >
                    {{ $page.props.status }}
                </Message>
                <form
                    class="space-y-6"
                    @submit.prevent="form.post('/forgot-password')"
                >
                    <div class="flex flex-col gap-2">
                        <label for="username">Email</label>
                        <InputText
                            v-model="form.email"
                            name="email"
                            label="Email"
                            type="email"
                            :errors="$page.props.errors"
                            required
                            autofocus
                        />
                    </div>
                    <div class="text-right">
                        <Button
                            type="submit"
                            label="Envoyer le lien de réinitialisation"
                            :disabled="form.processing"
                            :class="{ loading: form.processing }"
                        />
                    </div>
                </form>
            </template>
        </Card>
        <p class="text-center text-sm leading-6 mt-10 text-gray-500">
            Vous vous rappelez de votre mot de passe ?
            <Link
                href="/login"
                class="font-semibold text-primary-600 hover:text-primary-500"
            >
                Connectez-vous
            </Link>
        </p>
    </div>
</template>

<script>
import { Head } from '@inertiajs/vue3'
import GuestLayout from '../../Layouts/GuestLayout.vue'

export default {
    components: {
        Head
    },
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
