<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue'; 
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { LoaderCircle, Eye, EyeOff } from 'lucide-vue-next';
import { store } from '@/routes/login';

defineProps<{
    status?: string;
    canResetPassword?: boolean;
}>();

// Estado para mostrar/ocultar contraseña
const showPassword = ref(false);

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
    <div class="flex min-h-screen items-center justify-center bg-gray-100 p-4 font-sans">
        <div class="w-full max-w-md space-y-6 rounded-lg bg-white p-8 shadow-lg border border-gray-200">
            <div class="text-center">
                <h2 class="text-2xl font-bold text-black uppercase tracking-tight">
                    Inicia sesión
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Ingrese su usuario y contraseña para acceder
                </p>
            </div>

            <Head title="Iniciar sesión" />

            <div
                v-if="status"
                class="mb-4 text-center text-sm font-medium text-green-600 bg-green-50 p-2 rounded border border-green-200"
            >
                {{ status }}
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                
                <!-- CAMPO USUARIO (Actualizado: Ya no dice RFC) -->
                <div class="grid gap-2">
                    <Label for="username" class="text-black font-bold uppercase text-[10px] tracking-widest">Nombre de Usuario</Label>
                    <Input
                        id="username"
                        type="text"
                        name="username"
                        v-model="form.username"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="username"
                        placeholder="Escriba su usuario"
                        class="bg-white text-black placeholder-gray-400 border-gray-300 focus:ring-blue-500 h-11"
                    />
                    <InputError :message="form.errors.username" />
                </div>

                <!-- CAMPO CONTRASEÑA (Con observador de visibilidad) -->
                <div class="grid gap-2">
                    <Label for="password" class="text-black font-bold uppercase text-[10px] tracking-widest">Contraseña</Label>
                    <div class="relative">
                        <Input
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            name="password"
                            v-model="form.password"
                            required
                            :tabindex="2"
                            autocomplete="current-password"
                            placeholder="Ingrese su clave"
                            class="bg-white text-black placeholder-gray-400 border-gray-300 focus:ring-blue-500 h-11 pr-10"
                        />
                        <!-- Botón para conmutar visibilidad -->
                        <button 
                            type="button" 
                            @click="showPassword = !showPassword"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-blue-600 transition-colors"
                        >
                            <Eye v-if="!showPassword" class="h-4 w-4" />
                            <EyeOff v-else class="h-4 w-4" />
                        </button>
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <!-- CHECKBOX -->
                <div class="flex items-center">
                    <div class="flex items-center space-x-3">
                        <Checkbox 
                            id="remember" 
                            :checked="form.remember" 
                            @update:checked="(val: boolean) => form.remember = val"
                            :tabindex="3" 
                            class="border-gray-300 text-blue-600"
                        />
                        <Label for="remember" class="text-black font-medium text-sm cursor-pointer select-none">
                            Mantener sesión iniciada
                        </Label>
                    </div>
                </div>

                <Button
                    type="submit"
                    class="h-11 w-full bg-slate-900 text-white hover:bg-black font-bold uppercase text-[10px] tracking-[0.2em] shadow-md transition-all active:scale-95"
                    :tabindex="4"
                    :disabled="form.processing"
                >
                    <LoaderCircle
                        v-if="form.processing"
                        class="h-4 w-4 animate-spin mr-2"
                    />
                    Iniciar sesión
                </Button>
            </form>

            <!-- Pie de página integrado -->
            <div class="pt-4 border-t border-gray-100 text-center">
                <p class="text-[9px] font-medium text-gray-400 uppercase tracking-widest">
                    PJDyJR
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
input {
    border-width: 1px !important;
}
</style>