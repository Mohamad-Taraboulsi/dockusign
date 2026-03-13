import axios from 'axios';
import { ref, computed } from 'vue';
import type { DocumentField, FieldType } from '@/types/esign';

type HistoryEntry = {
    action: 'add' | 'update' | 'remove';
    field: DocumentField;
    previousData?: Partial<DocumentField>;
};

export function useDocumentEditor(documentId: string) {
    const fields = ref<DocumentField[]>([]);
    const selectedFieldId = ref<string | null>(null);
    const selectedRecipientId = ref<string | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);

    const undoStack = ref<HistoryEntry[]>([]);
    const redoStack = ref<HistoryEntry[]>([]);

    const selectedField = computed(() =>
        fields.value.find((f) => f.id === selectedFieldId.value) ?? null,
    );

    const canUndo = computed(() => undoStack.value.length > 0);
    const canRedo = computed(() => redoStack.value.length > 0);

    const baseUrl = `/esign/documents/${documentId}/fields`;

    function pushHistory(entry: HistoryEntry): void {
        undoStack.value.push(entry);
        redoStack.value = [];
    }

    async function fetchFields(): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const response = await axios.get<{ data: DocumentField[] }>(baseUrl);
            fields.value = response.data.data ?? response.data;
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Failed to fetch fields';
        } finally {
            loading.value = false;
        }
    }

    async function addField(fieldData: {
        type: FieldType;
        document_file_id: number;
        recipient_id: string;
        page_number: number;
        position_x: number;
        position_y: number;
        width: number;
        height: number;
        label?: string | null;
        placeholder?: string | null;
        is_required?: boolean;
        validation_rules?: Record<string, unknown> | null;
        options?: Record<string, unknown> | null;
    }): Promise<DocumentField | null> {
        error.value = null;
        try {
            const response = await axios.post<{ data: DocumentField }>(baseUrl, fieldData);
            const newField = response.data.data ?? response.data;
            fields.value.push(newField);
            pushHistory({ action: 'add', field: { ...newField } });
            selectedFieldId.value = newField.id;
            return newField;
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Failed to add field';
            return null;
        }
    }

    async function updateField(
        fieldId: string,
        data: Partial<DocumentField>,
    ): Promise<DocumentField | null> {
        error.value = null;
        const index = fields.value.findIndex((f) => f.id === fieldId);
        if (index === -1) return null;

        const previousData = { ...fields.value[index] };

        try {
            const response = await axios.put<{ data: DocumentField }>(
                `${baseUrl}/${fieldId}`,
                data,
            );
            const updatedField = response.data.data ?? response.data;
            fields.value[index] = updatedField;
            pushHistory({
                action: 'update',
                field: updatedField,
                previousData,
            });
            return updatedField;
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Failed to update field';
            return null;
        }
    }

    async function removeField(fieldId: string): Promise<boolean> {
        error.value = null;
        const index = fields.value.findIndex((f) => f.id === fieldId);
        if (index === -1) return false;

        const removedField = { ...fields.value[index] };

        try {
            await axios.delete(`${baseUrl}/${fieldId}`);
            fields.value.splice(index, 1);
            pushHistory({ action: 'remove', field: removedField });
            if (selectedFieldId.value === fieldId) {
                selectedFieldId.value = null;
            }
            return true;
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Failed to remove field';
            return false;
        }
    }

    function selectField(fieldId: string | null): void {
        selectedFieldId.value = fieldId;
    }

    async function undo(): Promise<void> {
        const entry = undoStack.value.pop();
        if (!entry) return;

        switch (entry.action) {
            case 'add': {
                // Undo add = remove the field
                const index = fields.value.findIndex((f) => f.id === entry.field.id);
                if (index !== -1) {
                    try {
                        await axios.delete(`${baseUrl}/${entry.field.id}`);
                        fields.value.splice(index, 1);
                    } catch {
                        // Re-push to undo stack if server call fails
                        undoStack.value.push(entry);
                        return;
                    }
                }
                break;
            }
            case 'update': {
                // Undo update = restore previous data
                if (entry.previousData) {
                    const index = fields.value.findIndex((f) => f.id === entry.field.id);
                    if (index !== -1) {
                        try {
                            const response = await axios.put<{ data: DocumentField }>(
                                `${baseUrl}/${entry.field.id}`,
                                entry.previousData,
                            );
                            fields.value[index] = response.data.data ?? response.data;
                        } catch {
                            undoStack.value.push(entry);
                            return;
                        }
                    }
                }
                break;
            }
            case 'remove': {
                // Undo remove = re-add the field
                try {
                    const response = await axios.post<{ data: DocumentField }>(baseUrl, entry.field);
                    const restoredField = response.data.data ?? response.data;
                    fields.value.push(restoredField);
                } catch {
                    undoStack.value.push(entry);
                    return;
                }
                break;
            }
        }

        redoStack.value.push(entry);
    }

    async function redo(): Promise<void> {
        const entry = redoStack.value.pop();
        if (!entry) return;

        switch (entry.action) {
            case 'add': {
                // Redo add = re-add the field
                try {
                    const response = await axios.post<{ data: DocumentField }>(baseUrl, entry.field);
                    const newField = response.data.data ?? response.data;
                    fields.value.push(newField);
                } catch {
                    redoStack.value.push(entry);
                    return;
                }
                break;
            }
            case 'update': {
                // Redo update = apply the updated data again
                const index = fields.value.findIndex((f) => f.id === entry.field.id);
                if (index !== -1) {
                    try {
                        const response = await axios.put<{ data: DocumentField }>(
                            `${baseUrl}/${entry.field.id}`,
                            entry.field,
                        );
                        fields.value[index] = response.data.data ?? response.data;
                    } catch {
                        redoStack.value.push(entry);
                        return;
                    }
                }
                break;
            }
            case 'remove': {
                // Redo remove = remove the field again
                const index = fields.value.findIndex((f) => f.id === entry.field.id);
                if (index !== -1) {
                    try {
                        await axios.delete(`${baseUrl}/${entry.field.id}`);
                        fields.value.splice(index, 1);
                    } catch {
                        redoStack.value.push(entry);
                        return;
                    }
                }
                break;
            }
        }

        undoStack.value.push(entry);
    }

    // Load fields on init
    fetchFields();

    return {
        fields,
        selectedFieldId,
        selectedRecipientId,
        selectedField,
        loading,
        error,
        canUndo,
        canRedo,
        fetchFields,
        addField,
        updateField,
        removeField,
        selectField,
        undo,
        redo,
    };
}
