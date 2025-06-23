import { logger } from '../logger';
import { CodeDescription } from '.';
import { IClientException } from './IClientException';

export class ClientExceptionImpl implements IClientException {
    name: string;
    code : string;
    message: string;
    stack?: string | undefined;

    constructor(
        error : CodeDescription, 
        detailsMessage : string | null = null
    ) {
        logger.error(error.message, detailsMessage)
        this.name = error.message
        this.code = error.code
        this.message = error.message + ", " + detailsMessage
        this.stack = error.message + ", " + detailsMessage
    }
}