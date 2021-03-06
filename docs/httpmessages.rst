.. index:: HTTP Messages

.. _HttpmessagesAnchor:

HTTP Messages
=============

The LightMVC Framework's HTTP message objects are the ``\Laminas\Diactoros\ServerRequest`` and the
``\Laminas\Diactoros\Response`` objects. These are PSR-7 compliant classes and are compatible with
PSR-15 compliant middleware.

.. index:: Request

.. _request:

ServerRequest Object
--------------------

In order to get a better understanding of the ``ServerRequest`` object, please see the
`Laminas documentation on server-side applications <https://docs.laminas.dev/laminas-diactoros/v2/usage/#server-side-applications>`_.

.. index:: Response

.. _response:

Response Object
---------------

The ``Response`` object makes it possible to add headers and provide content to the application's final response
to the client. Here is a simple example in order to do so:

.. code-block:: php

    $response = new Laminas\Diactoros\Response();

    // Write to the response body:
    $response->getBody()->write("Hello");

    // Multiple calls to write() append:
    $response->getBody()->write(" World"); // now "Hello World"

    // Add headers
    // Note: headers can be added to the response after the data has been written to the body
    $response = $response
        ->withHeader('Content-Type', 'text/plain')
        ->withAddedHeader('X-Custom-Header', 'example');

For further reading on the ``Response`` object, please see the
`Laminas documentation <https://docs.laminas.dev/laminas-diactoros/v2/usage/#manipulating-the-response>`_.
