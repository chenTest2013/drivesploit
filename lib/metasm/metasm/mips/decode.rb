#    This file is part of Metasm, the Ruby assembly manipulation suite
#    Copyright (C) 2007 Yoann GUILLOT
#
#    Licence is LGPL, see LICENCE in the top-level directory


require 'metasm/mips/opcodes'
require 'metasm/decode'

module Metasm
class MIPS
	def build_opcode_bin_mask(op)
		# bit = 0 if can be mutated by an field value, 1 if fixed by opcode
		op.bin_mask = 0
		op.args.each { |f|
			op.bin_mask |= @fields_mask[f] << @fields_shift[f]
		}
		op.bin_mask = 0xffffffff ^ op.bin_mask
	end

	def build_bin_lookaside
		lookaside = Array.new(256) { [] }
		@opcode_list.each { |op|
			build_opcode_bin_mask op

			b   = op.bin >> 24
			msk = op.bin_mask >> 24
			
			for i in b..(b | (255^msk))
				next if i & msk != b & msk
				lookaside[i] << op
			end
		}
		lookaside
	end

	def decode_findopcode(edata)
		# TODO relocations !!
		di = DecodedInstruction.new(self)
		val = edata.decode_imm(:u32, @endianness)
		edata.ptr -= 4
		di if di.opcode = @bin_lookaside[val >> 24].find { |op|
			(op.bin & op.bin_mask) == (val & op.bin_mask)
		}
	end

	def decode_instr_op(edata, di)
		# TODO relocations !!
		before_ptr = edata.ptr
		op = di.opcode
		di.instruction.opname = op.name
		val = edata.decode_imm(:u32, @endianness)

		field_val = proc { |f|
			r = (val >> @fields_shift[f]) & @fields_mask[f]
			# XXX do that cleanly (Expr.decode_imm)
			case f
			when :sa, :i16, :it
				((r >> 15) == 1) ? (r - (1 << 16)) : r
			when :i20
				((r >> 19) == 1) ? (r - (1 << 20)) : r
			when :i26
				((r >> 25) == 1) ? (r - (1 << 26)) : r
			else r
			end
		}

		op.args.each { |a|
			di.instruction.args << case a
			when :rs, :rt, :rd: Reg.new field_val[a]
			when :sa, :i16, :i20, :i26, :it: Expression[field_val[a]]
			when :rs_i16: Memref.new Reg.new(field_val[:rs]), field_val[:i16]
			when :ft: FpReg.new field_val[a]
			when :idm1, :idb: Expression['unsupported']
			else raise SyntaxError, "Internal error: invalid argument #{a} in #{op.name}"
			end
		}
		di.bin_length += edata.ptr - before_ptr

		di
	end

	# converts relative branch offsets to absolute addresses
	# if the jump is into edata, create a new label
	# else just add the offset +off+ of the instruction + its length (off may be an Expression)
	# assumes edata.ptr points just after the instruction (as decode_instr_op left it)
	# do not call twice on the same di !
	def decode_instr_interpret(program, off, edata, di)
		if di.opcode.props[:setip] and di.instruction.args.last.kind_of? Expression and di.opcode.name[0] != ?t
			delta = Expression[di.instruction.args.last, :<<, 2].reduce
			if delta.kind_of? ::Integer and edata.ptr + delta <= edata.length and edata.ptr + delta >= 0
				arg = program.label_at(edata, edata.ptr+delta, 'loc_%08x' % (edata.ptr+delta))
			else
				arg = Expression[[off, :+, di.bin_length], :+, delta].reduce
			end
			di.instruction.args[-1] = Expression[arg]
		end

		di
	end

	def emu_backtrace(di, off, value)
		symify = proc { |tg|
			case tg
			when Memref: Indirection.new(Expression[tg.base.to_s.to_sym, :+, tg.offset], :u32)
			when Reg: tg.to_s.to_sym
			else tg
			end
		}
		a = di.instruction.args.map { |arg| symify[arg] }

		case op = di.opcode.name
		when :TODO
		else nil
		end
	end

	def get_jump_targets(pgm, di, off)
		symify = proc { |tg|
			case tg
			when Memref: Indirection.new(Expression[tg.base.to_s.to_sym, :+, tg.offset], :u32)
			when Reg: tg.to_s.to_sym	# XXX $1 == $t0 == ...
			else tg
			end
		}
		[symify[di.instruction.args.last]]
	end
end
end
