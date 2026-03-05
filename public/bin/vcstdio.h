/**
*
*   Vitamin C Multi-User version 1.1
*   Copyright (c) 1985-1990
*   Creative Programming Consultants, Inc.
*   P.O. Box 112097
*   Carrollton, Texas  75006
*   (214) 416-6447
*   ALL RIGHTS RESERVED
*   Last Modification: .4 on 9/2/90 at 14:44:34
*
*   Name            vcstdio.h  --  Standard header file
*
*   Description     
*
**/

#ifndef VCSTDIO

#define VCSTDIO 1

#define UNIXV

#define VERSION "4.22 4/11/89 18:01:39"

#define STRING_H


/*------------------------------------------------------------------*/
/*--------------------ENVIRONMENT DEFINITIONS-----------------------*/
/*------------------------------------------------------------------*/

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *  */
/*                                                                  */
/*  OPERATING SYSTEMS                                               */
/*  =================                                               */
/*  MSDOS_LC............... Lattice v2.x or v3 Compiler             */
/*  MSDOS_MS4.............. Microsoft Ver 4.0 C Compiler            */
/*  MSDOS_MS5.............. Microsoft Ver 5.0 C compiler            */
/*  MSDOS_BTC.............. Borland Turbo C                         */
/*  MSDOS_CI............... Computer Innovations                    */
/*  MSDOS_AZ............... Aztec                                   */
/*  MSDOS_MW............... Mark Williams                           */
/*  OS2_MS5................ OS2 and MicroSoft 5.x                   */
/*  XENIX3................. Xenix V3                                */
/*  XENIX5................. Xenix V5                                */
/*  UNIXV.................. Unix V5                                 */
/*  BSD.................... BSD                                     */
/*  VMS.................... Digital VMS                             */
/*  PRIMOS................. Prime                                   */
/*                                                                  */
/*  OPERATING SYSTEM OPTIONS:                                       */
/*  (these are selected for the operating system)                   */
/*          TERMONLY....... Operating system is Terminal based      */
/*                                                                  */
/*          TTY............ Use tty to set terminal   \ | must pick */
/*          IOCTL.......... Use ioctl to set terminal  >| one and   */
/*                                                    /   only one  */
/*                                                                  */
/*          MEMCPY......... Has a memcpy command                    */
/*                                                                  */
/*          NONBLOCK....... \ |   keyrdy function         /|        */
/*          RDCHK..........  >|   must pick one and      < |        */
/*                          / |      only one!            \|        */
/*                                                                  */
/*          NAP............ \ |   has nap function for    /|        */
/*          NONAP..........  >|   delays - define TRUE   < |        */
/*                          / |   or FALSE (use with zd)  \|        */
/*                                                                  */
/*                                                                  */
/*  TERMINAL............... Use Terminal Drivers (Use with UNIX)    */
/*                          (Can be used with IBM to run both       */
/*                           with ansi driver and direct to memory) */
/*                                                                  */
/*  WINDOW................. Compile code to allow full window       */
/*                          support                                 */
/*                                                                  */
/*  NOUNSIGNED............. Compiler does not support unsigned      */
/*                          character                               */
/*                                                                  */
/*  USESHORT............... Use short integers (not recommended     */
/*                          can produce larger code). This also     */
/*                          makes the INTEGER define of input       */
/*                          expect a short integer                  */
/*                                                                  */
/*  SCRNBUFSIZ............. Size of screen buffer to use            */
/*                                                                  */
/*  BADREAD {value}........ What read returns for no characters     */
/*                          available - {value} usually 0 or -1     */
/*                                                                  */
/*  KEYRDY {value}......... Does the keyready function work         */
/*                          value should be TRUE or FALSE           */
/*                                                                  */
/*  REPEAT_KEYRDY.......... Repeat keyrdy() zt(termcap) number      */
/*                          of times to determine if another        */
/*                          key is available                        */
/*                                                                  */
/*  HASVOID................ Compiler has void data type             */
/*                                                                  */
/*                                                                  */
/*  NEWFWRITE.............. Use modified fwrite compiled in         */
/*  (non functional)        Vitamin C to catch output               */
/*                          going to the terminal through other     */
/*                          functions (like printf()). Cannot       */
/*                          be used with the USEFWRITE (see above)  */
/*                                                                  */
/*  NOMINMAX............... Stdio.h does not define the min()       */
/*                          and max() macros                        */
/*                                                                  */
/*  DATEPRINT.............. Date print string usually "%2d/%2d/%2d" */
/*  DATESCAN............... Date scan string usually "%d/%d/%d"     */
/*  DATEEDIT............... Date edit string usually "99/99/99"     */
/*  YEARFIRST.............. Print date yy/mm/dd   \                 */
/*  DAYFIRST............... Print date dd/mm/yy    > Pick only one  */
/*  MONTHFIRST............. Print date mm/dd/yy   /                 */
/*                                                                  */
/*  NOREDIRECT............. Use lower level input routine on IBM    */
/*                          this allow use of F11 and F12 key also  */
/*                          sets the vcshift key with the keyboard  */
/*                          flags. (only tested with TurboC) This   */
/*                          does not allow redirection.             */
/*                                                                  */
/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *  */

/* #define VCDEBUG */

#define TERMCAP         /* Must Define                              */
/*#define WINDOW*/
#define SBARS
#define REPEAT_KEYRDY
#define VCKEY_DEFS

#define DIALOG_H 

#ifdef MSDOS_LC
#define IBM
#define LC
#endif

#ifdef MSDOS_MS4
#define IBM
#define MS4
#endif

#ifdef MSDOS_MS5
#define IBM
#define MS5
#endif

#ifdef MSDOS_BTC
#define IBM
#define BTC
#endif

#ifdef MSDOS_CI
#define IBM
#define CI
#endif

#ifdef MSDOS_AZ
#define IBM
#define AZ
#endif

#ifdef MSDOS_MW
#define IBM
#define MW
#endif

#ifdef OS2_MS5
#define OS2
#define MS5
#endif

#ifdef XENIX3
#define UNIX
#define IOCTL
#define NONBLOCK
#define NOUNSIGNED
#define KEYRDY TRUE
#define BADREAD 0
#define PRINTDEV "/dev/vcprt"
#define TERMONLY
#define TERMINAL
#define SCRNBUFSIZ 3000
#endif

#ifdef XENIX5
#define UNIX
#define MEMCPY
#define IOCTL
#define NONBLOCK
#define KEYRDY TRUE
#define BADREAD 0
#define PRINTDEV "/dev/vcprt"
#define TERMONLY
#define TERMINAL
#define SCRNBUFSIZ 3000
#endif

#ifdef UNIXV
#define UNIX
#define MEMCPY
#define IOCTL
#define NONBLOCK
#define KEYRDY TRUE
#define BADREAD -1
#define HASVOID
#define PRINTDEV "/dev/vcprt"
#define TERMONLY
#define TERMINAL
#define SCRNBUFSIZ 3000
#endif

#ifdef BSD
#define UNIX
#define MEMCPY
#define TTY
#define NONBLOCK
#define KEYRDY TRUE
#define REPEAT_KEYRDY
#define BADREAD -1
#define PRINTDEV "/dev/vcprt"
#define TERMONLY
#define TERMINAL
#define SCRNBUFSIZ 3000
#endif

#ifdef VMS
#define KEYRDY TRUE
#define REPEAT_KEYRDY
#define PRINTDEV "/dev/vcprt"
#define TERMONLY
#define TERMINAL
#define SCRNBUFSIZ 3000
#endif

#ifdef PRIMOS
#define KEYRDY TRUE
#define BADREAD 0
#define REPEAT_KEYRDY
#define NOUNSIGNED
#define PRINTDEV "/dev/vcprt"
#define TERMONLY
#define TERMINAL
#define SCRNBUFSIZ 3000
#endif

#ifdef IBM
#define PRINTDEV "lpt1"
#define KEYRDY TRUE
#endif

#ifdef OS2
#define PRINTDEV "lpt1"
#define KEYRDY TRUE
#endif

/*------------------------------------------------------------------*/
/*---------------------Standard I/O Files---------------------------*/
/*------------------------------------------------------------------*/

#include <stdio.h>

#ifdef OS2
#define INCL_BASE
#include <os2.h>
#endif

#ifdef MS4
#define MSC
#endif

#ifdef MS5
#define MSC
#endif

#ifdef BTC
#define HASVOID
#define MSC
#endif

#ifdef MSC
#include <ctype.h>
#define NOMINMAX
#define VCF_RB "rb"
#define VCF_RT "rt"
#define VCF_R "r"
#define VCF_A "a"
#define VCF_WB "wb"
#define VCF_WT "wt"
#define VCF_W "w"
#define VCF_RW "r+"
#endif

#ifdef AZ
#include <ctype.h>
#define NOMINMAX
#define VCF_RB "r"
#define VCF_RT "r"
#define VCF_R "r"
#define VCF_A "a"
#define VCF_WB "w"
#define VCF_WT "w"
#define VCF_W "w"
#define VCF_RW "r+"
#endif

#ifdef LC
#include <ctype.h>
#define VCF_RB "rb"
#define VCF_RT "rt"
#define VCF_R "r"
#define VCF_A "a"
#define VCF_WB "wb"
#define VCF_WT "wt"
#define VCF_W "w"
#define VCF_RW "r+"
#endif

#ifdef CI
#define NOMINMAX
#define NULLFUNC NULL
#define VCF_RB "rb"
#define VCF_RT "rt"
#define VCF_R "r"
#define VCF_A "a"
#define VCF_WB "wb"
#define VCF_WT "wt"
#define VCF_W "w"
#define VCF_RW "r+"
#endif

#ifdef MW
#include <ctype.h>
#define NOMINMAX
#define VCF_RB "rb"
#define VCF_RT "rt"
#define VCF_R "r"
#define VCF_A "a"
#define VCF_WB "wb"
#define VCF_WT "wt"
#define VCF_W "w"
#define VCF_RW "r+"
#endif

#ifdef UNIX
#include <ctype.h>
#define NOMINMAX
#define VCF_RB "rb"
#define VCF_RT "r"
#define VCF_R "r"
#define VCF_A "a"
#define VCF_WB "wb"
#define VCF_WT "w"
#define VCF_W "w"
#define VCF_RW "r+"
#endif

#ifdef VMS
#include <ctype.h>
#define NOMINMAX
#define VCF_RB "rb"
#define VCF_RT "r"
#define VCF_R "r"
#define VCF_A "a"
#define VCF_WB "wb"
#define VCF_WT "w"
#define VCF_W "w"
#define VCF_RW "r+"
#endif

#ifdef PRIMOS
#include <ctype.h>
#define NOMINMAX
#define VCF_RB "r"
#define VCF_RT "r"
#define VCF_R "r"
#define VCF_A "a"
#define VCF_WB "w"
#define VCF_WT "w"
#define VCF_W "w"
#define VCF_RW "w"
#endif

#ifdef NOUNSIGNED
typedef char TEXT;
#else
typedef unsigned char TEXT;
#endif

#ifdef NOMINMAX
#ifndef min
#define min(a,b)  ((a) < (b) ? (a) : (b))
#endif
#ifndef max
#define max(a,b)  ((a) > (b) ? (a) : (b))
#endif
#endif

#ifndef VOID
#ifdef HASVOID
typedef void VOID;
#else
typedef int VOID;
#endif
#endif

#ifdef IBM
#define CR        "\n"
#else
#define CR        "\n\r"
#endif

#define OUTFUNC(x,y) vcout(x,y)
#define OUTDEF COUNT vcout()
#define INFUNC() vcin()
#define INDEF COUNT vcin()

#ifdef USESHORT
#define DATESCAN "%hd/%hd/%hd"
#else
#define DATESCAN "%d/%d/%d"
#endif
#define DATEPRINT "%2d/%2d/%2d"
#define DATEEDIT "99/99/99"
#define MONTHFIRST

#define DEFAULT -1

/*------------------------------------------------------------------*/
/*----------------------Typedef for Variables-----------------------*/
/*------------------------------------------------------------------*/

#define FAST register
#define PFAST register

#ifdef USESHORT
typedef unsigned short UCOUNT;
typedef short COUNT;
#else
typedef unsigned int UCOUNT;
typedef int COUNT;
#endif
typedef short SHORT;

#ifndef LONG
typedef unsigned long ULONG;
typedef long LONG;
#endif

typedef COUNT (*VALIDF)();
typedef TEXT *(*PFT)();
typedef TEXT (*PFC)();
typedef LONG (*PFL)();
typedef TEXT *TEXTPTR; 

#ifndef NULLFUNC
#define NULLFUNC (PFI) 0
#endif

#ifndef NULLTEXT
#define NULLTEXT (TEXT *)0
#define NULLSTR  '\0'
#endif

#define NULLWIN  (WPTR)NULL 


#define	PRIVATE		static
#define	PUBLIC		extern

#ifdef  IBM
#if defined(__COMPACT__) || defined(__LARGE__) || defined(__HUGE__)
#	define LDATA
#endif
#endif

/****************************************************************************
*
*	SEG(p)		Evaluates to the segment portion of an 8086 address.
*	OFF(p)		Evaluates to the offset portion of an 8086 address.
*	FP(s,o)		Creates a far pointer given a segment offset pair.
*	PHYS(p)		Evaluates to a long holding a physical address
*
****************************************************************************/
#ifdef	IBM
#define SEG(p)	( ((unsigned *)&(void far *)(p))[1] )
#define OFF(p)	( (unsigned)(p) )
#define FP(s,o)	( (void far *)( ((unsigned long)s << 16) + (unsigned long)o ))
#define PHYS(p)	( (unsigned long)OFF(p) + ((unsigned long)SEG(p) << 4))
#else
#define PHYS(p) 	(p)
#endif	

/****************************************************************************
*
*	NUMELE(array)		Evaluates to the array size in elements
*	LASTELE(array)		Evaluates to a pointer to the last element
*	INBOUNDS(array,p)	Evaluates to true if p points into the array
*	NBITS(type)		Returns the number of bits in a variable of the
*				indicated type
*	MAXINT			Evaluates to the value of the largest signed 
*				integer
*
****************************************************************************/
#define	NUMELE(a)	(sizeof(a)/sizeof(*(a)))
#define	LASTELE(a)	((a) + (NUMELE(a)-1))
#ifdef	LDATA
#define	TOOHIGH(a,p)	((long)PHYS(p) - (long)PHYS(a) > (long)(NUMELE(a)-1))
#define	TOOLOW(a,p)	((long)PHYS(p) - (long)PHYS(a) < 0)
#else
#define	TOOHIGH(a,p)	((long)(p) - (long)(a) > (long)(NUMELE(a)-1))
#define	TOOLOW(a,p)	((long)(p) - (long)(a) < 0)
#endif
#define	INBOUNDS(a,p)	( ! (TOOHIGH(a,p) || TOOLOW(a,p)) )

/* Evaluates true if the width of */
#define	_IS(t,x) 	(((t)1 << (x)) != 0)

/* variable of type t is < x.	  */
/* The != 0 assures that the      */
/* answer is 1 or 0		  */

#define	NBITS(t) (4 * (1 + _IS(t,4)  + _IS(t,8)  + _IS(t,12) + _IS(t,16) \
			 + _IS(t,20) + _IS(t,24) + _IS(t,28) + _IS(t,32)))

#define	MAXINT		(((unsigned)~0) >> 1)

/* General typedefs	*/
#ifdef  IBM
typedef void near	*nearptr;
typedef	void far	*farptr;
#endif

/*------------------------------------------------------------------*/
/*--------------------KEYBOARD DEFINITIONS--------------------------*/
/*------------------------------------------------------------------*/

#ifdef VCKEY_DEFS
#include "vckeys.h"
#endif

#ifdef IBM
#include <vckeyibm.h>
#endif

/*------------------------------------------------------------------*/
/*--------------------SPECIAL PROGRAM DEFINES-----------------------*/
/*------------------------------------------------------------------*/

#ifndef VCSTRIP
#include "vcdef.h"
#endif

/*------------------------------------------------------------------*/
/*---------------------Program Redefinitions------------------------*/
/*------------------------------------------------------------------*/

#define ERASE erase
#define GETTIME gettime
#define GETDATE getdates

/*------------------------------------------------------------------*/
/*----------------Contains all structure definitions----------------*/
/*------------------------------------------------------------------*/

#ifndef VCSTRIP
#include <vcstruct.h>
typedef struct WINF *WPTR;
#endif

#ifdef VCGET_DEFS
#include <vcget.h>
#endif

#ifdef SEL_DEFS
#include <vcselset.h>
#endif

#ifdef VCM_DEFS
#include <vcm.h>
#endif

#ifdef VCPRO_DEFS
#include <vcprokey.h>
#endif

/*------------------------------------------------------------------*/
/*-----------------------Error Definitions -------------------------*/
/*------------------------------------------------------------------*/

#ifdef VCERROR_DEFS
#include <vcerror.h>
#endif

/*------------------------------------------------------------------*/
/*------------------SPECIAL FILES AND VARIABLES---------------------*/
/*------------------------------------------------------------------*/

#ifdef STRING_H
#include <string.h>
#endif

#ifdef VCLINT
#include <vclint.h>
#endif

#ifndef NCSTRIP
#include "ncdef.h"
#endif

#ifdef NCLINT
#include <nclint.h>
#endif

#ifndef VCSTRIP
#ifndef vcglobal
#include <vcextern.h>
#endif
#endif

#endif


