<template>
    <v-container class="text-center">
        <div class="text-h5 text-md-h3 mt-12">
            Praktikum Informatika
        </div>

        <v-btn class="mt-8" color="primary" large :href="loginURL">
            <v-icon left>mdi-microsoft</v-icon>
            Login
        </v-btn>
    </v-container>
</template>

<script>
export default {
    name: 'Home',

    computed: {
        loginURL() {
            return `${process.env.VUE_APP_API_URL}/auth`
        }
    }
}
</script>
