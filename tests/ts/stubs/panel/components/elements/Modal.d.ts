import * as React from 'react';
export interface RequiredModalProps { visible: boolean; onDismissed: () => void; appear?: boolean; top?: boolean }
export interface ModalProps extends RequiredModalProps { dismissable?: boolean; closeOnEscape?: boolean; closeOnBackground?: boolean; showSpinnerOverlay?: boolean }
declare const Modal: React.FC<ModalProps>;
export default Modal;
