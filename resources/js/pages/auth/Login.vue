<script setup lang="ts">
import InputError from '@/components/InputError.vue';
// import TextLink from '@/components/TextLink.vue'; // Eliminado, ya no se usa
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
// import { register } from '@/routes'; // Eliminado, ya no se usa "registrarse"
import { store } from '@/routes/login';
// import { request } from '@/routes/password'; // Eliminado, ya no se usa "olvidó contraseña"
import { Form, Head } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean; // Se mantiene en props por si el padre lo envía, pero no se usa
    canRegister: boolean; // Se mantiene en props por si el padre lo envía, pero no se usa
}>();
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gray-100 p-4">
        <div class="w-full max-w-md space-y-6 rounded-lg bg-white p-8 shadow-lg">
            <div class="text-center">
                <h2 class="text-2xl font-bold text-black">
                    Inicia sesión de cuenta
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Ingrese el correo y contraseña para iniciar sesión
                </p>
            </div>

            <Head title="Iniciar sesión" />

            <div
                v-if="status"
                class="mb-4 text-center text-sm font-medium text-green-600"
            >
                {{ status }}
            </div>

            <Form
                v-bind="store.form()"
                :reset-on-success="['password']"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
            >
                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="email" class="text-black">Correo electrónico</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="email"
                            placeholder="email@example.com"
                            class="bg-white text-black placeholder-gray-500 border-gray-300"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password" class="text-black">Contraseña</Label>
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            required
                            :tabindex="2"
                            autocomplete="current-password"
                            placeholder="Contraseña"
                            class="bg-white text-black placeholder-gray-500 border-gray-300"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="flex items-center">
                        <Label for="remember" class="flex items-center space-x-3">
                            <Checkbox id="remember" name="remember" :tabindex="3" />
                            <span class="text-black">Recuérdame</span>
                        </Label>
                    </div>

                    <Button
                        type="submit"
                        class="mt-4 w-full bg-black text-white hover:bg-gray-800"
                        :tabindex="4"
                        :disabled="processing"
                        data-test="login-button"
                    >
                        <LoaderCircle
                            v-if="processing"
                            class="h-4 w-4 animate-spin"
                        />
                        Iniciar sesión
                    </Button>
                </div>

                </Form>
        </div>
    </div>
</template>