<template>
    <div>
        <Card>
            <template #title>
                Réinitialiser votre mot de passe
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
                    @submit.prevent="form.post('/reset-password')"
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
                    <div class="flex flex-col gap-2">
                        <label for="password">Mot de passe</label>
                        <InputText
                            v-model="form.password"
                            name="password"
                            type="password"
                            :errors="$page.props.errors"
                            required
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="password">Confirmer le mot de passe</label>
                        <InputText
                            v-model="form.password_confirmation"
                            name="password_confirmation"
                            type="password"
                            :errors="$page.props.errors"
                            required
                        />
                    </div>
                    <div class="text-right">
                        <Button
                            type="submit"
                            label="Soumettre le nouveau mot de passe"
                            :disabled="form.processing"
                            :class="{ loading: form.processing }"
                        />
                    </div>
                </form>
            </template>
        </Card>

        <p class="text-center text-sm leading-6 mt-10 text-gray-500">
            Got your memory back?
            <Link
                href="/login"
                class="font-semibold text-primary-600 hover:text-primary-500"
            >
                Login now
            </Link>
        </p>
    </div>
</template>

<script>
import GuestLayout from '../../Layouts/GuestLayout.vue'

export default {
    layout: GuestLayout,
    props: ['email', 'token'],
    data () {
        return {
            form: this.$inertia.form({
                email: '',
                password: '',
                password_confirmation: '',
                remember: false,
                token: this.token
            })
        }
    }
}
</script>
