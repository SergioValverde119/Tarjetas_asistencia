<script setup lang="ts">
// CORRECCIÓN 1: Cambiado 'Components' a 'components' para coincidir con tu estructura
import InputError from '@/components/InputError.vue'; 
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { store } from '@/routes/login';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

// Usamos useForm directamente para controlar los campos
const form = useForm({
    username: '', 
    password: '',
    remember: false,
});

const submit = () => {
    
    form.post(store().url, {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gray-100 p-4">
        <div class="w-full max-w-md space-y-6 rounded-lg bg-white p-8 shadow-lg">
            <div class="text-center">
                <h2 class="text-2xl font-bold text-black">
                    Inicia sesión
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Ingrese su RFC y contraseña
                </p>
            </div>

            <Head title="Iniciar sesión" />

            <div
                v-if="status"
                class="mb-4 text-center text-sm font-medium text-green-600"
            >
                {{ status }}
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                
                <!-- CAMPO USUARIO / RFC -->
                <div class="grid gap-2">
                    <Label for="username" class="text-black">RFC / Usuario</Label>
                    <Input
                        id="username"
                        type="text"
                        name="username"
                        v-model="form.username"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="username"
                        placeholder="Ingrese su RFC"
                        class="bg-white text-black placeholder-gray-500 border-gray-300"
                    />
                    <InputError :message="form.errors.username" />
                </div>

                <!-- CAMPO CONTRASEÑA -->
                <div class="grid gap-2">
                    <Label for="password" class="text-black">Contraseña</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        v-model="form.password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Número de empleado"
                        class="bg-white text-black placeholder-gray-500 border-gray-300"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <!-- CHECKBOX -->
                <div class="flex items-center">
                    <div class="flex items-center space-x-3">
                        <!-- CORRECCIÓN 2: Agregado tipo ': boolean' al parámetro val -->
                        <Checkbox 
                            id="remember" 
                            :checked="form.remember" 
                            @update:checked="(val: boolean) => form.remember = val"
                            :tabindex="3" 
                        />
                        <Label for="remember" class="text-black font-normal cursor-pointer">
                            Recuérdame
                        </Label>
                    </div>
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full bg-black text-white hover:bg-gray-800"
                    :tabindex="4"
                    :disabled="form.processing"
                    data-test="login-button"
                >
                    <LoaderCircle
                        v-if="form.processing"
                        class="h-4 w-4 animate-spin mr-2"
                    />
                    Iniciar sesión
                </Button>
            </form>
        </div>
    </div>
</template>