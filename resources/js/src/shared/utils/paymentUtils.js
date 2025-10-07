/**
 * General payment utility functions
 * @namespace PaymentUtils
 */

/**
 * Checks if the current context is the WordPress block editor
 * @return {boolean} True if in block editor context, false otherwise
 * @example
 * if (isEditorContext()) {
 *     // Load editor-specific functionality
 * }
 */
export const isEditorContext = () => wc?.wcBlocksData?.isEditor();
