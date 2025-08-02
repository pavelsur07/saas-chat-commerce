// assets/chat-center/components/SendMessageForm.tsx

import React from 'react';
import { useForm } from 'react-hook-form';
import axios from 'axios';
import { toast } from 'react-toastify';

type Props = {
    clientId: string;
    onMessageSent: () => void;
};

const SendMessageForm: React.FC<Props> = ({ clientId, onMessageSent }) => {
    const { register, handleSubmit, reset } = useForm<{ message: string }>();

    const onSubmit = async (data: { message: string }) => {

        const message = data.message?.trim(); // ✂️ обрезаем пробелы
        console.error( message );

        if (!clientId) return;
        try {
            await axios.post(`/api/messages/${clientId}`, { text: message });
            reset();
            toast.success('Сообщение отправлено');
            onMessageSent();
        } catch (e) {
            toast.error('Ошибка при отправке');
            console.error('Ошибка при POST /api/messages:', e);
        }
    };

    return (
        <form onSubmit={handleSubmit(onSubmit)} className="flex gap-2">
            <input
                {...register('message', { required: true })}
                placeholder="Введите сообщение..."
                className="flex-1 px-4 py-2 border rounded"
            />
            <button type="submit" className="bg-blue-600 text-white px-4 py-2 rounded">
                ➤
            </button>
        </form>
    );
};

export default SendMessageForm;
