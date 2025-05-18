<template>
    <div>
        <Card>
            <template #title>
                Connectez-vous à votre compte
            </template>
            <template #content>
                <form
                    class="space-y-6"
                    @submit.prevent="form.post('/login')"
                >
                    <Message v-if="$page.props.status">
                        {{ $page.props.status }}
                    </Message>
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
                        <small
                            v-if="$page.props.errors?.email"
                            class="text-red-500"
                        >{{ $page.props.errors?.email }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="username">Mot de passe</label>
                        <InputText
                            v-model="form.password"
                            name="password"
                            label="Password"
                            type="password"
                            :errors="$page.props.errors"
                            required
                        />
                        <small
                            v-if="$page.props.errors?.password"
                            class="text-red-500"
                        >{{ $page.props.errors?.password }}</small>
                    </div>
                    <div class="flex justify-between">
                        <div class="flex items-center">
                            <Checkbox
                                v-model="form.remember"
                                input-id="remember"
                                name="remember"
                                value="true"
                            />
                            <label
                                for="remember"
                                class="ml-2"
                            >
                                Rester connecté
                            </label>
                        </div>
                        <Link
                            href="/forgot-password"
                            class="font-semibold text-sm text-primary-600 hover:text-primary-500"
                        >
                            Mot de passe oublié?
                        </Link>
                    </div>
                    <div class="text-right">
                        <Button
                            type="submit"
                            label="Connexion"
                            :disabled="form.processing"
                            :class="{ loading: form.processing }"
                        />
                    </div>
                </form>
            </template>
        </Card>

        <p class="text-center text-sm leading-6 mt-10 text-gray-500">
            Pas encore de compte?
            <Link
                href="/register"
                class="font-semibold text-primary-600 hover:text-primary-500"
            >
                Créez en un maintenant
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
