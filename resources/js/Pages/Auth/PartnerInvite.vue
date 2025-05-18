<template>
    <div>
        <Card>
            <template #title>
                Inviter votre partenaire
            </template>
            <template #subtitle>
                Piloulou est un jeu à jouer à 2, invitez votre partenaire et découvrez les noms de bébé que vous choisissez en commun!
            </template>
            <template #content>
                <form
                    class="space-y-6"
                    @submit.prevent="submit"
                >
                    <Message v-if="$page.props.status">
                        {{ $page.props.status }}
                    </Message>
                    <div class="flex flex-col gap-2">
                        <label for="username">Adresse Email</label>
                        <InputText
                            v-model="form.email"
                            name="email"
                            placeholder="conjoint@email.com"
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

                    <div class="text-right">
                        <Button
                            label="Envoyer l'invitation"
                            type="submit"
                            :loading="form.processing"
                        />
                    </div>
                </form>
            </template>
        </Card>
    </div>
</template>

<script>
export default {
    data () {
        return {
            form: this.$inertia.form({
                email: ''
            })
        }
    },

    methods: {
        submit () {
            this.form.post('/invite', {
                onSuccess: () => {
                    this.form.reset()
                }
            })
        }
    }
}
</script>
